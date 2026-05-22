@extends('layouts.site')

@section('title', 'Privacy Policy — '.config('app.name'))

@push('styles')
<style>
    .legal-wrap { padding: 2rem 0 4rem; }
    .legal-nav { display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1.25rem 0; }
    .legal-logo { font-family:"Outfit",sans-serif;font-weight:700;font-size:1.25rem;color:var(--text); }
    .legal-logo span { color:var(--accent); }
    .legal-card { max-width: 920px; margin: 1.5rem auto 0; }
    .legal-card h1 { font-family:"Outfit",sans-serif;font-size:clamp(2rem,4vw,3rem);line-height:1.08;margin-bottom:.75rem; }
    .legal-card .lead { color:var(--muted);font-size:1.05rem;margin-bottom:2rem; }
    .legal-section { margin-top:1.75rem;padding-top:1.35rem;border-top:1px solid var(--border); }
    .legal-section h2 { font-family:"Outfit",sans-serif;font-size:1.25rem;margin-bottom:.6rem; }
    .legal-section p, .legal-section li { color:var(--muted); }
    .legal-section ul { padding-left:1.25rem;display:grid;gap:.45rem; }
    .legal-meta { color:var(--muted);font-size:.9rem;margin-top:.5rem; }
</style>
@endpush

@section('content')
<div class="container legal-wrap">
    <nav class="legal-nav">
        <a class="legal-logo" href="{{ url('/') }}">{{ config('app.name') }}<span>.</span></a>
        <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
            <a class="btn btn-ghost" href="{{ url('/') }}">Home</a>
            <a class="btn btn-primary" href="{{ route('login') }}">Admin login</a>
        </div>
    </nav>

    <main class="card legal-card">
        <h1>Privacy Policy</h1>
        <p class="lead">
            This Privacy Policy explains how {{ config('app.name') }} collects, uses, stores, and protects information when you use our web, mobile, POS, inventory, accounting, and reporting services.
        </p>
        <p class="legal-meta">Last updated: {{ now()->format('F j, Y') }}</p>

        <section class="legal-section">
            <h2>Information we collect</h2>
            <p>We collect information needed to create accounts, run business workspaces, process sales, manage stock, and provide reports.</p>
            <ul>
                <li>Account details such as name, email address, password, role, and authentication data.</li>
                <li>Business information such as business name, branch details, logo, phone number, address, VAT/tax settings, and public shop settings.</li>
                <li>Operational records such as products, categories, purchases, suppliers, customers, sales, returns, expenses, accounts, journals, payroll, proposals, quotations, and reports.</li>
                <li>Uploaded files such as product images, business logos, loan documents, proposal assets, and other files you submit.</li>
                <li>Device and technical data such as IP address, browser/device type, API logs, crash information, and sync metadata.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>How we use information</h2>
            <ul>
                <li>To provide and maintain POS, inventory, accounting, reporting, payroll, quotation, proposal, and business-service features.</li>
                <li>To authenticate users, manage roles, protect accounts, and prevent unauthorized access.</li>
                <li>To synchronize data between the mobile app and backend services, including offline sale queues.</li>
                <li>To process subscriptions, payments, loan applications, support requests, and extra-service applications.</li>
                <li>To improve product reliability, investigate errors, and comply with legal or regulatory obligations.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>Payments and third parties</h2>
            <p>
                Payment processing may be handled by trusted providers such as Paystack. We do not store full card details on our servers. Third-party providers process payment information under their own security and privacy terms.
            </p>
        </section>

        <section class="legal-section">
            <h2>Location, camera, photos, and biometrics</h2>
            <p>
                The mobile app may request permissions such as camera, photo library, Face ID/Touch ID, or location-related purpose strings where required by iOS and bundled libraries. Camera and photos are used for business logos and product images. Biometric authentication is used only to help you sign in securely. Where device location is not used by a feature, the permission text may still be present because a bundled library references location APIs.
            </p>
        </section>

        <section class="legal-section">
            <h2>Data sharing</h2>
            <p>We do not sell your business data. We may share limited data with:</p>
            <ul>
                <li>Service providers that host, secure, process payments, or support the platform.</li>
                <li>Team members you invite into your business workspace, according to their role.</li>
                <li>Regulators, courts, or law enforcement where legally required.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>Data security and retention</h2>
            <p>
                We use reasonable administrative, technical, and organizational safeguards to protect your information. We keep business records for as long as needed to provide the service, support accounting/reporting use cases, comply with legal requirements, or resolve disputes.
            </p>
        </section>

        <section class="legal-section">
            <h2>Your choices</h2>
            <ul>
                <li>You can update business profile data, products, customers, staff, and other records from the app or admin dashboard.</li>
                <li>You can request help with account access, correction, export, or deletion where permitted by law.</li>
                <li>You can disable device permissions through your device settings, though some features may stop working.</li>
            </ul>
        </section>

        <section class="legal-section">
            <h2>Contact</h2>
            <p>
                For privacy questions or data requests, contact the {{ config('app.name') }} support team through the admin dashboard or your normal support channel.
            </p>
        </section>
    </main>
</div>
@endsection
