{{-- Shared admin UI (workspace + portfolio) --}}
<script>
try {
    var t = localStorage.getItem('adm-theme');
    var m = (!t || t === 'system')
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : (t === 'dark' ? 'dark' : 'light');
    document.documentElement.setAttribute('data-adm-theme', m);
    document.documentElement.setAttribute('data-adm-theme-pref', t || 'system');
} catch (e) {}
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=Outfit:wght@500;600;700&display=swap" rel="stylesheet">
<style>
:root {
    --adm-bg: #f1f5f9;
    --adm-surface: #ffffff;
    --adm-border: #e2e8f0;
    --adm-text: #0f172a;
    --adm-muted: #64748b;
    --adm-accent: #4f46e5;
    --adm-accent-soft: #eef2ff;
    --adm-success: #059669;
    --adm-danger: #dc2626;
    --adm-sidebar: #0f172a;
    --adm-sidebar-text: #e2e8f0;
    --adm-sidebar-muted: #94a3b8;
    --radius: 14px;
    --shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    --shadow-lg: 0 16px 48px -12px rgba(15, 23, 42, 0.14);
}
*, *::before, *::after { box-sizing: border-box; }
body.adm-body {
    margin: 0;
    font-family: "DM Sans", system-ui, sans-serif;
    background: var(--adm-bg);
    color: var(--adm-text);
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
}
a { color: var(--adm-accent); text-decoration: none; }
a:hover { text-decoration: underline; }
.adm-topbar {
    background: linear-gradient(180deg, #ffffff 0%, #fafbff 100%);
    border-bottom: 1px solid var(--adm-border);
    padding: 0.85rem 1.5rem;
    box-shadow: 0 1px 0 rgba(255,255,255,0.8) inset;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.adm-brand {
    font-family: "Outfit", sans-serif;
    font-weight: 700;
    font-size: 1.125rem;
    color: var(--adm-text);
}
.adm-brand span { color: var(--adm-accent); }
.adm-shell { display: flex; min-height: calc(100vh - 57px); }
.adm-sidebar {
    width: 260px;
    flex-shrink: 0;
    background: var(--adm-sidebar);
    color: var(--adm-sidebar-text);
    padding: 1.25rem 0;
    display: flex;
    flex-direction: column;
}
.adm-biz-name {
    padding: 0 1.25rem 1rem;
    font-family: "Outfit", sans-serif;
    font-weight: 600;
    font-size: 0.95rem;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    margin-bottom: 0.75rem;
}
.adm-role-pill {
    display: inline-block;
    margin-top: 0.35rem;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 0.2rem 0.5rem;
    border-radius: 6px;
    background: rgba(79, 70, 229, 0.3);
    color: #c7d2fe;
}
.adm-nav a {
    display: flex;
    align-items: center;
    gap: 0.65rem;
    padding: 0.65rem 1.25rem;
    color: var(--adm-sidebar-muted);
    font-size: 0.9rem;
    font-weight: 500;
    text-decoration: none;
    border-left: 3px solid transparent;
}
.adm-nav a:hover { color: #fff; background: rgba(255,255,255,0.04); text-decoration: none; }
.adm-nav a.active { color: #fff; background: rgba(79, 70, 229, 0.2); border-left-color: #a5b4fc; }
.adm-nav-icon { width: 1.25rem; text-align: center; opacity: 0.85; }
.adm-sidebar-foot {
    margin-top: auto;
    padding: 1rem 1.25rem;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-size: 0.8rem;
    color: var(--adm-sidebar-muted);
}
.adm-sidebar-foot a { color: #93c5fd; }
.adm-main { flex: 1; padding: 1.5rem 2rem 3rem; min-width: 0; }
.adm-page-title {
    font-family: "Outfit", sans-serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0 0 0.25rem;
}
.adm-page-desc { color: var(--adm-muted); margin: 0 0 1.5rem; font-size: 0.95rem; }
.adm-card {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.25rem;
}
.adm-flash {
    padding: 0.85rem 1rem;
    border-radius: var(--radius);
    margin-bottom: 1.25rem;
    font-size: 0.9rem;
}
.adm-flash.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
.adm-flash.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }
.adm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.55rem 1rem;
    font-size: 0.875rem;
    font-weight: 600;
    border-radius: 8px;
    border: none;
    cursor: pointer;
    font-family: inherit;
    text-decoration: none;
}
.adm-btn:hover { opacity: 0.92; text-decoration: none; }
.adm-btn-primary { background: var(--adm-accent); color: #fff; }
.adm-btn-ghost { background: #fff; color: var(--adm-text); border: 1px solid var(--adm-border); }
.adm-btn-danger { background: #fef2f2; color: var(--adm-danger); border: 1px solid #fecaca; }
.adm-table-wrap { overflow-x: auto; border-radius: var(--radius); border: 1px solid var(--adm-border); }
.adm-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.adm-table th, .adm-table td { padding: 0.75rem 1rem; text-align: left; border-bottom: 1px solid var(--adm-border); }
.adm-table th { background: #f8fafc; font-weight: 600; color: var(--adm-muted); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.04em; }
.adm-table tr:last-child td { border-bottom: none; }
.adm-input, .adm-select, .adm-textarea {
    width: 100%;
    padding: 0.6rem 0.75rem;
    border: 1px solid var(--adm-border);
    border-radius: 8px;
    font-size: 0.9rem;
    font-family: inherit;
}
.adm-input:focus, .adm-select:focus, .adm-textarea:focus {
    outline: none;
    border-color: var(--adm-accent);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.18);
}
.adm-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--adm-muted); margin-bottom: 0.35rem; }
.adm-field { margin-bottom: 1rem; }
.adm-grid { display: grid; gap: 1rem; }
@media (min-width: 768px) {
    .adm-grid.cols-2 { grid-template-columns: repeat(2, 1fr); }
    .adm-grid.cols-4 { grid-template-columns: repeat(4, 1fr); }
}
.adm-stat {
    padding: 1.25rem;
    border-radius: var(--radius);
    background: linear-gradient(135deg, var(--adm-accent-soft), #fff);
    border: 1px solid var(--adm-border);
}
.adm-stat-val { font-family: "Outfit", sans-serif; font-size: 1.75rem; font-weight: 700; color: var(--adm-accent); }
.adm-stat-lbl { font-size: 0.8rem; color: var(--adm-muted); margin-top: 0.25rem; }
.adm-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; align-items: center; }
.adm-portfolio-hero {
    max-width: 960px;
    margin: 0 auto 2rem;
    padding: 2rem 1.5rem;
    border-radius: var(--radius);
    background: linear-gradient(135deg, #eef2ff 0%, #ffffff 55%, #f8fafc 100%);
    border: 1px solid var(--adm-border);
    box-shadow: var(--shadow);
}
.adm-portfolio-grid {
    display: grid;
    gap: 1rem;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
}
.adm-biz-card {
    background: var(--adm-surface);
    border: 1px solid var(--adm-border);
    border-radius: var(--radius);
    padding: 1.25rem;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    transition: box-shadow 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}
.adm-biz-card:hover {
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
    border-color: rgba(79, 70, 229, 0.25);
}
.adm-biz-card h3 { font-family: "Outfit", sans-serif; font-size: 1.1rem; margin: 0; }
.adm-inline-form { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: flex-end; }
.adm-inline-form .adm-field { margin: 0; flex: 1; min-width: 160px; }

.adm-portfolio-main { max-width: 1100px; margin: 0 auto; }

.adm-topbar-start { display: flex; align-items: center; gap: 0.65rem; min-width: 0; }
.adm-icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 2.5rem;
    height: 2.5rem;
    padding: 0;
    border-radius: 10px;
    border: 1px solid var(--adm-border);
    background: var(--adm-surface);
    color: var(--adm-text);
    cursor: pointer;
    font-size: 1.1rem;
    line-height: 1;
    font-family: inherit;
}
.adm-icon-btn:hover { background: var(--adm-accent-soft); }
.adm-nav-toggle { display: none; }
.adm-user-email {
    color: var(--adm-muted);
    font-size: 0.875rem;
    max-width: 42vw;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.adm-nav-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.45);
    z-index: 150;
    border: none;
    padding: 0;
    cursor: pointer;
}

html[data-adm-theme="dark"] {
    --adm-bg: #0f1419;
    --adm-surface: #161d27;
    --adm-border: #2a3441;
    --adm-text: #e8edf4;
    --adm-muted: #94a3b8;
    --adm-accent: #818cf8;
    --adm-accent-soft: rgba(129, 140, 248, 0.12);
    --adm-success: #34d399;
    --adm-danger: #f87171;
    --adm-sidebar: #0b0f14;
    --adm-sidebar-text: #e2e8f0;
    --adm-sidebar-muted: #8b9cb3;
    --shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
    --shadow-lg: 0 16px 48px -12px rgba(0, 0, 0, 0.55);
}
html[data-adm-theme="dark"] .adm-topbar {
    background: linear-gradient(180deg, #161d27 0%, #121820 100%);
    border-bottom-color: var(--adm-border);
    box-shadow: none;
}
html[data-adm-theme="dark"] .adm-flash.ok {
    background: rgba(6, 95, 70, 0.35);
    color: #a7f3d0;
    border-color: rgba(52, 211, 153, 0.35);
}
html[data-adm-theme="dark"] .adm-flash.err {
    background: rgba(127, 29, 29, 0.35);
    color: #fecaca;
    border-color: rgba(248, 113, 113, 0.35);
}
html[data-adm-theme="dark"] .adm-table th {
    background: #1a222d;
    color: var(--adm-muted);
}
html[data-adm-theme="dark"] .adm-btn-ghost {
    background: var(--adm-surface);
    color: var(--adm-text);
    border-color: var(--adm-border);
}
html[data-adm-theme="dark"] .adm-stat {
    background: linear-gradient(135deg, var(--adm-accent-soft), var(--adm-surface));
}
html[data-adm-theme="dark"] .adm-portfolio-hero {
    background: linear-gradient(135deg, rgba(129, 140, 248, 0.12) 0%, var(--adm-surface) 55%, #121820 100%);
}

@media (max-width: 767px) {
    .adm-nav-toggle { display: inline-flex; }
    .adm-topbar {
        position: sticky;
        top: 0;
        z-index: 160;
        padding: 0.65rem 1rem;
    }
    .adm-shell { flex-direction: column; min-height: calc(100vh - 52px); position: relative; }
    .adm-sidebar {
        position: fixed;
        z-index: 200;
        left: 0;
        top: 0;
        bottom: 0;
        width: min(288px, 88vw);
        transform: translateX(-100%);
        transition: transform 0.22s ease;
        box-shadow: var(--shadow-lg);
        padding-top: 4.5rem;
    }
    body.adm-nav-open .adm-sidebar { transform: translateX(0); }
    body.adm-nav-open .adm-nav-overlay { display: block; }
    .adm-main { padding: 1rem 1rem 2.5rem; }
    .adm-page-title { font-size: 1.25rem; }
    .adm-btn { min-height: 44px; padding: 0.6rem 1rem; }
    .adm-nav a { min-height: 44px; }
    .adm-portfolio-hero { padding: 1.25rem 1rem; margin-bottom: 1.25rem; }
    .adm-portfolio-grid { grid-template-columns: 1fr; }
    .adm-user-email { max-width: 36vw; font-size: 0.8rem; }
}
@media (min-width: 768px) {
    .adm-nav-overlay { display: none !important; }
}
</style>
