<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\SendProposalPdf;
use App\Models\Business;
use App\Models\PlatformSetting;
use App\Models\Proposal;
use App\Services\BusinessTokenService;
use App\Services\PdfRenderer;
use App\Services\ProposalAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProposalController extends Controller
{
    public function __construct(
        private readonly PdfRenderer $pdf,
        private readonly BusinessTokenService $tokens,
    ) {}

    public function index(Request $request, Business $business): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 30), 1), 100);
        $query = Proposal::query()->where('business_id', $business->id)->orderByDesc('id');
        $paginator = $query->paginate($perPage);
        $paginator->setCollection(
            $paginator->getCollection()->map(fn (Proposal $p) => $this->proposalSummary($p))
        );

        return response()->json($paginator);
    }

    public function generateAi(Request $request, Business $business, ProposalAiService $ai): JsonResponse
    {
        $data = $request->validate([
            'prompt' => ['required', 'string', 'min:10', 'max:8000'],
        ]);

        $cost = PlatformSetting::getInt('token_proposal_ai_cost', 10);
        $business->refresh();
        if ($cost > 0 && (int) $business->token_balance < $cost) {
            return response()->json([
                'message' => 'Insufficient token balance.',
                'balance' => (int) $business->token_balance,
                'required' => $cost,
            ], 402);
        }

        try {
            $out = $ai->generateDraft($business, $data['prompt']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        if ($cost > 0) {
            try {
                $this->tokens->debit($business, $request->user(), 'proposal_ai', $cost, [
                    'prompt_chars' => strlen($data['prompt']),
                ]);
            } catch (RuntimeException $e) {
                if ($e->getMessage() === 'INSUFFICIENT_TOKENS') {
                    $business->refresh();

                    return response()->json([
                        'message' => 'Insufficient token balance.',
                        'balance' => (int) $business->token_balance,
                        'required' => $cost,
                    ], 402);
                }

                throw $e;
            }
        }

        $business->refresh();

        return response()->json([
            'data' => $out,
            'token_balance' => (int) $business->token_balance,
            'tokens_charged' => $cost,
        ]);
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $data = $this->validatedPayload($request);
        $proposal = Proposal::query()->create([
            'business_id' => $business->id,
            'title' => $data['title'],
            'client_name' => $data['client_name'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'body_html' => $data['body_html'],
            'ai_prompt' => $data['ai_prompt'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'version' => 1,
        ]);

        return response()->json(['data' => $this->proposalDetail($proposal)], 201);
    }

    public function show(Business $business, Proposal $proposal): JsonResponse
    {
        $this->assertBelongs($business, $proposal);

        return response()->json(['data' => $this->proposalDetail($proposal)]);
    }

    public function update(Request $request, Business $business, Proposal $proposal): JsonResponse
    {
        $this->assertBelongs($business, $proposal);
        $data = $this->validatedPayload($request);

        if (isset($data['client_version']) && (int) $data['client_version'] !== (int) $proposal->version) {
            return response()->json([
                'message' => 'Version conflict',
                'server' => $this->proposalDetail($proposal->fresh()),
            ], 409);
        }

        $proposal->fill([
            'title' => $data['title'],
            'client_name' => $data['client_name'] ?? null,
            'client_email' => $data['client_email'] ?? null,
            'body_html' => $data['body_html'],
            'ai_prompt' => $data['ai_prompt'] ?? null,
            'status' => $data['status'] ?? $proposal->status,
        ]);
        $proposal->version = (int) $proposal->version + 1;
        $proposal->save();

        return response()->json(['data' => $this->proposalDetail($proposal->fresh())]);
    }

    public function destroy(Business $business, Proposal $proposal): JsonResponse
    {
        $this->assertBelongs($business, $proposal);
        $proposal->delete();

        return response()->json(['ok' => true]);
    }

    public function pdf(Business $business, Proposal $proposal): BinaryFileResponse
    {
        $this->assertBelongs($business, $proposal);
        $html = View::make('pdf.proposal', [
            'business' => $business,
            'proposal' => $proposal,
            'bodyHtml' => $proposal->body_html,
        ])->render();

        $safe = preg_replace('/[^a-zA-Z0-9._-]+/', '-', $proposal->title) ?? 'proposal';
        $safe = substr($safe, 0, 80);

        return $this->pdf->downloadResponse($html, $safe.'.pdf');
    }

    public function email(Request $request, Business $business, Proposal $proposal): JsonResponse
    {
        $this->assertBelongs($business, $proposal);
        $data = $request->validate([
            'to' => ['required', 'email'],
            'message' => ['nullable', 'string', 'max:2000'],
        ]);

        $html = View::make('pdf.proposal', [
            'business' => $business,
            'proposal' => $proposal,
            'bodyHtml' => $proposal->body_html,
        ])->render();
        $binary = $this->pdf->renderBinary($html);

        Mail::to($data['to'])->send(new SendProposalPdf(
            $business,
            $proposal,
            $binary,
            $data['message'] ?? null,
        ));

        $proposal->status = 'sent';
        $proposal->save();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'body_html' => ['required', 'string', 'max:200000'],
            'ai_prompt' => ['nullable', 'string', 'max:8000'],
            'status' => ['nullable', 'string', 'in:draft,sent'],
            'client_version' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function assertBelongs(Business $business, Proposal $proposal): void
    {
        abort_if((int) $proposal->business_id !== (int) $business->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalSummary(Proposal $p): array
    {
        return [
            'uuid' => $p->uuid,
            'title' => $p->title,
            'client_name' => $p->client_name,
            'status' => $p->status,
            'version' => (int) $p->version,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function proposalDetail(Proposal $p): array
    {
        return [
            'uuid' => $p->uuid,
            'title' => $p->title,
            'client_name' => $p->client_name,
            'client_email' => $p->client_email,
            'body_html' => $p->body_html,
            'ai_prompt' => $p->ai_prompt,
            'status' => $p->status,
            'version' => (int) $p->version,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
