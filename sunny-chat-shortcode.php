<?php
/**
 * Shortcode [sunny_chat] — Interface chat Sunny Pool
 * Version redesign UX/UI : Drawers, Quick Suggestions, Glassmorphism
 *
 * Usage sur une page WordPress : [sunny_chat]
 */

// ========== SHORTCODE CHAT SUNNY ==========
add_shortcode('sunny_chat', 'sunny_chat_shortcode');

function sunny_chat_shortcode() {
    if (!is_user_logged_in()) {
         $login_url = home_url('/connexion/') . '?redirect_to=' . urlencode(get_permalink());
        $signup_url = home_url('/register/');
        return '<div style="text-align:center;padding:40px 20px;">'
             . '<p style="color:#d4af37;font-size:1.1em;font-weight:600;">Connectez-vous pour parler à Sunny 🌞</p>'
             . '<p><a href="' . esc_url($login_url) . '" style="display:inline-block;padding:12px 28px;background:#d4af37;color:#1a1600;border-radius:10px;text-decoration:none;font-weight:700;">Se connecter</a></p>'
             . '<p style="color:#94a3b8;font-size:0.9em;margin-top:16px;">Pas encore de compte ? <a href="' . esc_url($signup_url) . '" style="color:#eab308;">Créer un abonnement</a></p>'
             . '</div>';
    }

    $user_id = get_current_user_id();
    $pools   = get_posts([
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [['key' => 'proprietaire', 'value' => $user_id, 'compare' => '=']]
    ]);

    $has_pool         = !empty($pools);
    $selected_pool_id = isset($_GET['pool_id']) ? intval($_GET['pool_id']) : (!empty($pools) ? $pools[0]->ID : 0);

    $pool_valid    = false;
    $selected_pool = null;
    foreach ($pools as $pool) {
        if ($pool->ID == $selected_pool_id) {
            $pool_valid    = true;
            $selected_pool = $pool;
            break;
        }
    }
    if (!$pool_valid && !empty($pools)) {
        $selected_pool_id = $pools[0]->ID;
        $selected_pool    = $pools[0];
    }

    $pool_volume = $selected_pool ? get_field('volume', $selected_pool->ID) : null;
    $pool_titre  = $selected_pool ? $selected_pool->post_title : 'votre piscine';

    $api_url = esc_url(rest_url('sunny-pool/v1/chat'));
    $nonce   = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <div class="sunny-chat-wrapper" id="sunny-chat-root">

    <!-- ===== EXTERNAL RESOURCES ===== -->
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>

    <!-- ===== STYLES ===== -->
    <style>
    /* ════════════════════════════════════════════════════════════
       SUNNY CHAT — REFONTE UX/UI v3.0
       Layout 100dvh · Glassmorphism · Micro-interactions
       ════════════════════════════════════════════════════════════ */

    :root {
        --gold:         #d4af37;
        --gold-light:   #ffd700;
        --gold-soft:    #f5d76e;
        --gold-dim:     rgba(212, 175, 55, 0.18);
        --gold-border:  rgba(212, 175, 55, 0.32);
        --gold-glow:    rgba(212, 175, 55, 0.45);

        --bg-deep:      #0b0d12;
        --bg-mid:       #14171f;
        --bg-card:      #1a1e28;
        --bg-elevated:  #21262f;
        --bg-input:     #232730;

        --surface-1:    rgba(255, 255, 255, 0.04);
        --surface-2:    rgba(255, 255, 255, 0.06);
        --surface-3:    rgba(255, 255, 255, 0.08);

        --glass-bg:     rgba(20, 23, 31, 0.72);
        --glass-border: rgba(255, 255, 255, 0.08);
        --glass-blur:   saturate(180%) blur(22px);

        --text-main:    #eef0f5;
        --text-muted:   #8a8f9e;
        --text-faint:   #5d6271;

        --user-grad:    linear-gradient(135deg, #d4af37 0%, #b8941f 100%);
        --sunny-grad:   linear-gradient(160deg, rgba(212,175,55,0.10) 0%, rgba(212,175,55,0.04) 100%);
        --header-grad:  linear-gradient(180deg, rgba(11,13,18,0.92) 0%, rgba(11,13,18,0.78) 100%);

        --radius-sm:    8px;
        --radius:       14px;
        --radius-lg:    20px;
        --radius-xl:    28px;

        --shadow-sm:    0 2px 8px rgba(0,0,0,0.25);
        --shadow:       0 8px 24px rgba(0,0,0,0.35);
        --shadow-lg:    0 16px 48px rgba(0,0,0,0.45);
        --shadow-gold:  0 8px 32px rgba(212,175,55,0.25);

        --ease:         cubic-bezier(0.4, 0, 0.2, 1);
        --ease-spring:  cubic-bezier(0.34, 1.56, 0.64, 1);
        --t-fast:       150ms;
        --t-base:       240ms;
        --t-slow:       420ms;

        --header-h:     64px;
        --sidebar-w:    72px;
        --sidebar-expanded-w: 260px;
        --mobilenav-h:  60px;
    }

    /* ── RESET SCOPÉ ───────────────────────────────────────────── */
    .sunny-chat-wrapper, .sunny-chat-wrapper *,
    .sunny-chat-wrapper *::before, .sunny-chat-wrapper *::after {
        box-sizing: border-box;
    }

    .sunny-chat-wrapper {
        position: fixed;
        inset: 0;
        width: 100vw;
        height: 100vh;
        height: 100dvh;
        overflow: hidden;
        font-family: 'Manrope', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        font-size: 15px;
        line-height: 1.55;
        color: var(--text-main);
        background:
            radial-gradient(ellipse 80% 60% at 20% 0%, rgba(212,175,55,0.08), transparent 60%),
            radial-gradient(ellipse 60% 50% at 100% 100%, rgba(212,175,55,0.05), transparent 60%),
            linear-gradient(180deg, var(--bg-deep) 0%, #07080b 100%);
        display: grid;
        grid-template-columns: var(--sidebar-w) 1fr;
        grid-template-rows: var(--header-h) 1fr;
        grid-template-areas:
            "sidebar header"
            "sidebar main";
        z-index: 9999;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* Décor : halos lumineux animés */
    .sunny-chat-wrapper::before,
    .sunny-chat-wrapper::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        filter: blur(80px);
        opacity: 0.35;
        pointer-events: none;
        z-index: 0;
        animation: float-orb 18s var(--ease) infinite;
    }
    .sunny-chat-wrapper::before {
        width: 420px; height: 420px;
        background: radial-gradient(circle, rgba(212,175,55,0.4), transparent 70%);
        top: -100px; left: 10%;
    }
    .sunny-chat-wrapper::after {
        width: 360px; height: 360px;
        background: radial-gradient(circle, rgba(80,120,200,0.25), transparent 70%);
        bottom: -120px; right: 15%;
        animation-delay: -9s;
    }

    @keyframes float-orb {
        0%, 100% { transform: translate(0,0) scale(1); }
        50%      { transform: translate(40px,30px) scale(1.1); }
    }

    /* ── SIDEBAR (Desktop) — ChatGPT-style toggle ─────────────── */
    .sunny-sidebar {
        grid-area: sidebar;
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border-right: 1px solid var(--glass-border);
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 12px 8px;
        gap: 8px;
        z-index: 10;
        position: relative;
        width: var(--sidebar-w);
        transition: width var(--t-slow) var(--ease);
        overflow: hidden;
    }
    .sunny-sidebar.expanded {
        width: var(--sidebar-expanded-w);
        align-items: stretch;
        padding: 12px 12px;
    }

    /* Toggle button */
    .sunny-sidebar-toggle {
        width: 40px; height: 40px;
        border: none;
        border-radius: var(--radius-sm);
        background: var(--surface-2);
        color: var(--text-muted);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all var(--t-fast) var(--ease);
        flex-shrink: 0;
        align-self: center;
    }
    .sunny-sidebar.expanded .sunny-sidebar-toggle { align-self: flex-end; }
    .sunny-sidebar-toggle:hover { background: var(--surface-3); color: var(--text-main); }
    .sunny-sidebar-toggle .material-symbols-outlined { font-size: 22px; transition: transform var(--t-base) var(--ease); }
    .sunny-sidebar.expanded .sunny-sidebar-toggle .material-symbols-outlined { transform: rotate(180deg); }

    .sunny-sidebar-btn {
        width: 48px; height: 48px;
        border: none;
        border-radius: var(--radius);
        background: var(--user-grad);
        color: #1a1410;
        font-size: 0;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: var(--shadow-gold);
        transition: transform var(--t-base) var(--ease-spring), box-shadow var(--t-base) var(--ease), width var(--t-slow) var(--ease);
        flex-shrink: 0;
    }
    .sunny-sidebar.expanded .sunny-sidebar-btn {
        width: 100%; height: 44px;
        font-size: 13px;
        gap: 8px;
        justify-content: flex-start;
        padding: 0 14px;
    }
    .sunny-sidebar-btn:hover { transform: scale(1.04); }
    .sunny-sidebar-btn:active { transform: scale(0.97); }
    .sunny-sidebar-btn .material-symbols-outlined { font-size: 22px; flex-shrink: 0; }
    .sunny-sidebar.expanded .sunny-sidebar-btn .sidebar-label { display: inline; font-weight: 700; }
    .sunny-sidebar-btn .sidebar-label { display: none; }

    .sunny-sidebar-nav {
        display: flex; flex-direction: column;
        gap: 2px;
        flex: 1;
        margin-top: 8px;
        width: 100%;
    }

    .sunny-sidebar-link, .sunny-sidebar-footer .sunny-sidebar-link {
        width: 48px; height: 44px;
        border: none;
        border-radius: var(--radius-sm);
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 0;
        position: relative;
        gap: 0;
        padding: 0;
        transition: background var(--t-fast) var(--ease), color var(--t-fast) var(--ease), width var(--t-slow) var(--ease);
        flex-shrink: 0;
        text-decoration: none;
    }
    .sunny-sidebar.expanded .sunny-sidebar-link {
        width: 100%; height: 42px;
        font-size: 13px;
        gap: 10px;
        justify-content: flex-start;
        padding: 0 14px;
    }
    /* Tooltip (collapsed only) */
    .sunny-sidebar-link::after {
        content: attr(data-tooltip, '');
        position: absolute;
        left: calc(100% + 12px);
        top: 50%;
        transform: translateY(-50%) translateX(-4px);
        background: var(--bg-elevated);
        color: var(--text-main);
        font-size: 12px;
        padding: 6px 10px;
        border-radius: 6px;
        white-space: nowrap;
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--t-fast), transform var(--t-fast);
        box-shadow: var(--shadow);
        z-index: 100;
    }
    .sunny-sidebar.expanded .sunny-sidebar-link::after { display: none; }
    .sunny-sidebar-link:hover {
        background: var(--surface-2);
        color: var(--gold-light);
    }
    .sunny-sidebar-link:hover::after {
        opacity: 1;
        transform: translateY(-50%) translateX(0);
    }
    .sunny-sidebar-link .material-symbols-outlined { font-size: 22px; flex-shrink: 0; }
    .sunny-sidebar-link .sidebar-label { display: none; font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .sunny-sidebar.expanded .sunny-sidebar-link .sidebar-label { display: inline; }

    /* Sidebar section dividers */
    .sunny-sidebar-divider {
        width: 32px; height: 1px;
        background: var(--glass-border);
        margin: 6px auto;
        transition: width var(--t-slow) var(--ease);
    }
    .sunny-sidebar.expanded .sunny-sidebar-divider { width: 100%; }

    /* Sidebar section label */
    .sunny-sidebar-section-label {
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: var(--text-faint);
        padding: 8px 14px 4px;
        display: none;
    }
    .sunny-sidebar.expanded .sunny-sidebar-section-label { display: block; }

    /* Sidebar footer */
    .sunny-sidebar-footer {
        display: flex; flex-direction: column;
        gap: 2px;
        width: 100%;
        padding-top: 8px;
        border-top: 1px solid var(--glass-border);
        margin-top: 4px;
    }

    /* Logout button */
    .sunny-sidebar-logout {
        width: 48px; height: 42px;
        border: none;
        border-radius: var(--radius-sm);
        background: transparent;
        color: var(--text-muted);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        font-size: 0;
        gap: 0;
        padding: 0;
        transition: all var(--t-fast) var(--ease);
        text-decoration: none;
    }
    .sunny-sidebar.expanded .sunny-sidebar-logout {
        width: 100%; height: 42px;
        font-size: 13px;
        gap: 10px;
        justify-content: flex-start;
        padding: 0 14px;
    }
    .sunny-sidebar-logout:hover { background: rgba(255,94,94,0.12); color: #ff8585; }
    .sunny-sidebar-logout .material-symbols-outlined { font-size: 22px; flex-shrink: 0; }
    .sunny-sidebar-logout .sidebar-label { display: none; font-weight: 500; }
    .sunny-sidebar.expanded .sunny-sidebar-logout .sidebar-label { display: inline; }

    /* ── HEADER ─────────────────────────────────────────────────── */
    .sunny-chat-header {
        grid-area: header;
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 0 20px;
        background: var(--header-grad);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border-bottom: 1px solid var(--glass-border);
        z-index: 10;
        position: relative;
    }

    .sunny-avatar {
        width: 42px; height: 42px;
        border-radius: 50%;
        background: var(--user-grad);
        display: flex; align-items: center; justify-content: center;
        font-size: 22px;
        flex-shrink: 0;
        box-shadow: 0 0 0 2px rgba(11,13,18,0.9), 0 0 0 3px var(--gold-border), 0 0 20px var(--gold-glow);
        animation: avatar-pulse 3s var(--ease) infinite;
    }
    @keyframes avatar-pulse {
        0%, 100% { box-shadow: 0 0 0 2px rgba(11,13,18,0.9), 0 0 0 3px var(--gold-border), 0 0 20px var(--gold-glow); }
        50%      { box-shadow: 0 0 0 2px rgba(11,13,18,0.9), 0 0 0 3px var(--gold-border), 0 0 32px var(--gold-glow); }
    }

    .sunny-header-info {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
    .sunny-header-info h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
        line-height: 1.2;
    }
    .sunny-status-row {
        display: flex; align-items: center; gap: 8px;
        margin-top: 2px;
        flex-wrap: wrap;
    }
    .sunny-status-text {
        font-size: 12px;
        color: var(--text-muted);
        transition: color var(--t-fast);
    }
    .sunny-status-text:hover { color: var(--gold-light); }

    .sunny-pool-selector-wrap select {
        background: var(--surface-2);
        border: 1px solid var(--glass-border);
        color: var(--text-main);
        font-size: 12px;
        padding: 4px 10px;
        border-radius: 999px;
        cursor: pointer;
        font-family: inherit;
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-pool-selector-wrap select:hover {
        background: var(--surface-3);
        border-color: var(--gold-border);
    }
    .sunny-pool-selector-wrap select:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px var(--gold-dim);
    }

    .sunny-header-actions {
        display: flex;
        gap: 4px;
    }
    .sunny-hdr-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        background: var(--surface-1);
        border: 1px solid transparent;
        border-radius: 999px;
        color: var(--text-muted);
        font-family: inherit;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        text-decoration: none;
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-hdr-link:hover {
        background: var(--surface-2);
        color: var(--text-main);
        border-color: var(--glass-border);
        transform: translateY(-1px);
    }
    .sunny-hdr-link:active { transform: translateY(0); }
    .sunny-hdr-link.active {
        background: var(--gold-dim);
        color: var(--gold-light);
        border-color: var(--gold-border);
    }
    .sunny-hdr-link .material-symbols-outlined { font-size: 18px; }

    /* ── MAIN CHAT AREA ─────────────────────────────────────────── */
    .sunny-chat-main {
        grid-area: main;
        display: flex !important;
        flex-direction: column !important;
        overflow: hidden !important;
        position: relative;
        margin-left: 0 !important;
        min-height: 0;
    }

    /* ── MESSAGES ───────────────────────────────────────────────── */
    .sunny-chat-messages {
        flex: 1;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 24px clamp(16px, 5vw, 48px) 16px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        scroll-behavior: smooth;
        scrollbar-width: thin;
        scrollbar-color: var(--surface-3) transparent;
        position: relative;
    }
    .sunny-chat-messages::-webkit-scrollbar { width: 8px; }
    .sunny-chat-messages::-webkit-scrollbar-track { background: transparent; }
    .sunny-chat-messages::-webkit-scrollbar-thumb {
        background: var(--surface-3);
        border-radius: 4px;
    }
    .sunny-chat-messages::-webkit-scrollbar-thumb:hover { background: var(--gold-border); }

    /* Bulles — base */
    .chat-bubble {
        max-width: min(78%, 680px);
        padding: 12px 16px;
        border-radius: var(--radius-lg);
        font-size: 14.5px;
        line-height: 1.55;
        word-wrap: break-word;
        position: relative;
        animation: bubble-in var(--t-slow) var(--ease-spring) backwards;
        animation-delay: calc(var(--i, 0) * 40ms);
    }
    @keyframes bubble-in {
        from { opacity: 0; transform: translateY(12px) scale(0.96); }
        to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Animation échelonnée pour les nouveaux messages */
    .sunny-chat-messages > * { animation-delay: 0ms; }

    .chat-bubble.user {
        align-self: flex-end;
        background: var(--user-grad);
        color: #1a1410;
        font-weight: 500;
        border-bottom-right-radius: 6px;
        box-shadow: var(--shadow-gold);
    }

    .chat-bubble.sunny {
        align-self: flex-start;
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        color: var(--text-main);
        border-bottom-left-radius: 6px;
        box-shadow: var(--shadow-sm);
    }
    .chat-bubble.sunny strong { color: var(--gold-light); font-weight: 700; }
    .chat-bubble.sunny a { color: var(--gold-light); text-decoration: underline; text-underline-offset: 2px; }
    .chat-bubble.sunny .sunny-rich {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .chat-bubble.sunny .sunny-rich-message {
        margin: 0;
        white-space: pre-wrap;
    }
    .chat-bubble.sunny .sunny-rich-section {
        padding: 10px 12px;
        border-radius: 12px;
        border: 1px solid var(--glass-border);
        background: rgba(255, 255, 255, 0.02);
    }
    .chat-bubble.sunny .sunny-rich-title {
        margin: 0 0 8px;
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: var(--gold-soft);
    }
    .chat-bubble.sunny .sunny-rich-list {
        margin: 0;
        padding-left: 18px;
        display: grid;
        gap: 8px;
    }
    .chat-bubble.sunny .sunny-rich-list li {
        margin: 0;
    }
    .chat-bubble.sunny .sunny-rich-item-title {
        font-weight: 700;
        margin-right: 6px;
        color: #f6f8fc;
    }
    .chat-bubble.sunny .sunny-rich-item-description {
        color: var(--text-main);
    }
    .chat-bubble.sunny .sunny-rich-badge {
        display: inline-block;
        margin-left: 8px;
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.02em;
        border: 1px solid transparent;
    }
    .chat-bubble.sunny .sunny-rich-badge.high {
        color: #ffb3b3;
        border-color: rgba(255, 94, 94, 0.5);
        background: rgba(255, 94, 94, 0.16);
    }
    .chat-bubble.sunny .sunny-rich-badge.medium {
        color: #ffd39d;
        border-color: rgba(255, 159, 67, 0.5);
        background: rgba(255, 159, 67, 0.16);
    }
    .chat-bubble.sunny .sunny-rich-badge.low {
        color: #f5e7b3;
        border-color: rgba(245, 215, 110, 0.5);
        background: rgba(245, 215, 110, 0.14);
    }
    .chat-bubble.sunny .sunny-rich-link-list {
        margin: 0;
        padding: 0;
        list-style: none;
        display: grid;
        gap: 8px;
    }
    .chat-bubble.sunny .sunny-rich-link {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 11px;
        border-radius: 10px;
        border: 1px solid var(--gold-border);
        background: rgba(212, 175, 55, 0.1);
        text-decoration: none;
        font-weight: 600;
        width: fit-content;
    }
    .chat-bubble.sunny .sunny-rich-link:hover {
        background: rgba(212, 175, 55, 0.16);
    }
    .chat-bubble.sunny .sunny-rich-diagnosis {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }
    .chat-bubble.sunny .sunny-rich-diagnosis p {
        margin: 0;
    }

    .chat-bubble.system {
        align-self: center;
        max-width: 90%;
        background: var(--surface-1);
        border: 1px dashed var(--glass-border);
        color: var(--text-muted);
        font-size: 13px;
        text-align: center;
        padding: 8px 14px;
        border-radius: 999px;
    }
    .chat-bubble.system a { color: var(--gold-light); }

    /* Indicateur de frappe réaliste */
    .typing-indicator {
        align-self: flex-start;
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        border-bottom-left-radius: 6px;
        padding: 14px 18px;
        display: inline-flex;
        gap: 5px;
        align-items: center;
        animation: bubble-in var(--t-slow) var(--ease-spring);
    }
    .typing-indicator span {
        width: 7px; height: 7px;
        background: var(--gold);
        border-radius: 50%;
        opacity: 0.4;
        animation: typing-bounce 1.3s var(--ease) infinite;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.18s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.36s; }
    @keyframes typing-bounce {
        0%, 60%, 100% { transform: translateY(0); opacity: 0.4; }
        30%           { transform: translateY(-7px); opacity: 1; }
    }

    /* Alertes */
    .sunny-alertes {
        align-self: stretch;
        display: flex; flex-direction: column;
        gap: 6px;
        max-width: 78%;
        animation: bubble-in var(--t-slow) var(--ease-spring);
    }
    .sunny-alerte {
        padding: 10px 14px;
        border-radius: var(--radius);
        font-size: 13.5px;
        font-weight: 500;
        border-left: 3px solid;
        background: var(--surface-1);
        backdrop-filter: blur(10px);
    }
    .sunny-alerte.faible  { border-color: #f5d76e; color: #f5d76e; background: rgba(245,215,110,0.08); }
    .sunny-alerte.moyenne { border-color: #ff9f43; color: #ffb976; background: rgba(255,159,67,0.10); }
    .sunny-alerte.haute,
    .sunny-alerte.critique { border-color: #ff5e5e; color: #ff8585; background: rgba(255,94,94,0.12); }

    /* ── FAB SCROLL TO BOTTOM ──────────────────────────────────── */
    .sunny-scroll-fab {
        position: absolute;
        right: 24px;
        bottom: 16px;
        width: 42px; height: 42px;
        border-radius: 50%;
        border: 1px solid var(--glass-border);
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        color: var(--gold-light);
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        box-shadow: var(--shadow);
        opacity: 0;
        transform: translateY(8px) scale(0.9);
        pointer-events: none;
        transition: opacity var(--t-base) var(--ease), transform var(--t-base) var(--ease-spring), background var(--t-fast);
        z-index: 5;
    }
    .sunny-scroll-fab.visible {
        opacity: 1;
        transform: translateY(0) scale(1);
        pointer-events: auto;
    }
    .sunny-scroll-fab:hover {
        background: var(--gold-dim);
        border-color: var(--gold-border);
    }
    .sunny-scroll-fab .material-symbols-outlined { font-size: 22px; }

    /* ── INPUT AREA ────────────────────────────────────────────── */
    .sunny-chat-input-area {
        padding: 12px clamp(16px, 5vw, 48px) 16px;
        background: linear-gradient(180deg, transparent 0%, rgba(11,13,18,0.6) 30%, var(--bg-deep) 100%);
        backdrop-filter: blur(12px);
        position: relative;
        z-index: 6;
    }

    .sunny-suggestions {
        display: flex;
        gap: 8px;
        padding-bottom: 10px;
        overflow-x: auto;
        scrollbar-width: none;
        scroll-behavior: smooth;
        mask-image: linear-gradient(90deg, transparent 0, black 16px, black calc(100% - 16px), transparent 100%);
    }
    .sunny-suggestions::-webkit-scrollbar { display: none; }

    .sunny-suggestion-pill {
        flex-shrink: 0;
        padding: 7px 14px;
        background: var(--surface-1);
        border: 1px solid var(--glass-border);
        color: var(--text-muted);
        border-radius: 999px;
        font-family: inherit;
        font-size: 12.5px;
        font-weight: 500;
        cursor: pointer;
        white-space: nowrap;
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-suggestion-pill:hover {
        background: var(--gold-dim);
        color: var(--gold-light);
        border-color: var(--gold-border);
        transform: translateY(-2px);
    }
    .sunny-suggestion-pill:active { transform: translateY(0); }

    /* Image preview compact */
    .sunny-image-preview {
        display: none;
        align-items: center;
        gap: 12px;
        margin-bottom: 10px;
        padding: 8px 10px;
        background: var(--surface-2);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        animation: bubble-in var(--t-base) var(--ease-spring);
    }
    .sunny-image-preview.active { display: flex; }
    .sunny-image-preview img {
        width: 44px; height: 44px;
        object-fit: cover;
        border-radius: 8px;
        border: 1px solid var(--glass-border);
    }
    .sunny-image-preview .img-meta { flex: 1; min-width: 0; }
    .sunny-image-meta-label { font-size: 13px; font-weight: 600; color: var(--text-main); }
    .sunny-image-meta-sub { font-size: 11.5px; color: var(--text-muted); }
    .sunny-image-remove {
        width: 28px; height: 28px;
        border: none;
        background: var(--surface-2);
        color: var(--text-muted);
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        transition: all var(--t-fast);
    }
    .sunny-image-remove:hover { background: rgba(255,94,94,0.2); color: #ff8585; }

    .sunny-file-input { display: none !important; }

    /* Input unifié */
    .sunny-input-unified {
        display: flex;
        align-items: flex-end;
        gap: 8px;
        padding: 8px;
        background: var(--bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-xl);
        box-shadow: var(--shadow);
        transition: border-color var(--t-base), box-shadow var(--t-base);
    }
    .sunny-input-unified:focus-within {
        border-color: var(--gold-border);
        box-shadow: 0 0 0 4px var(--gold-dim), var(--shadow);
    }

    .sunny-attach-btn, .sunny-send-btn {
        flex-shrink: 0;
        width: 40px; height: 40px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex; align-items: center; justify-content: center;
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-attach-btn {
        background: transparent;
        color: var(--text-muted);
    }
    .sunny-attach-btn:hover {
        background: var(--surface-3);
        color: var(--gold-light);
        transform: rotate(15deg);
    }
    .sunny-attach-btn .material-symbols-outlined { font-size: 22px; }

    .sunny-send-btn {
        background: var(--user-grad);
        color: #1a1410;
        box-shadow: 0 4px 12px var(--gold-glow);
    }
    .sunny-send-btn:hover:not(:disabled) {
        transform: scale(1.08) rotate(-15deg);
        box-shadow: 0 6px 20px var(--gold-glow);
    }
    .sunny-send-btn:active:not(:disabled) { transform: scale(0.95); }
    .sunny-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background: var(--surface-3);
        color: var(--text-faint);
        box-shadow: none;
    }
    .sunny-send-btn .material-symbols-outlined { font-size: 20px; }

    #sunny-input {
        flex: 1;
        min-height: 40px;
        max-height: 120px;
        padding: 10px 8px;
        background: transparent;
        border: none;
        outline: none;
        color: var(--text-main);
        font-family: inherit;
        font-size: 15px;
        line-height: 1.45;
        resize: none;
        scrollbar-width: thin;
        scrollbar-color: var(--surface-3) transparent;
    }
    #sunny-input::placeholder { color: var(--text-faint); }

    /* ── MOBILE BOTTOM NAV ─────────────────────────────────────── */
    .sunny-mobile-nav {
        display: none;
        position: fixed;
        bottom: 0; left: 0; right: 0;
        height: var(--mobilenav-h);
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border-top: 1px solid var(--glass-border);
        z-index: 50;
        padding-bottom: env(safe-area-inset-bottom, 0);
    }
    .sunny-mobile-nav-btn {
        flex: 1;
        background: none;
        border: none;
        color: var(--text-muted);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2px;
        font-family: inherit;
        font-size: 10.5px;
        font-weight: 500;
        cursor: pointer;
        transition: color var(--t-fast), background var(--t-fast);
        text-decoration: none;
    }
    .sunny-mobile-nav-btn .material-symbols-outlined { font-size: 22px; }
    .sunny-mobile-nav-btn:hover { color: var(--text-main); }
    .sunny-mobile-nav-btn.active { color: var(--gold-light); }
    .sunny-mobile-nav-btn:active { background: var(--surface-2); }

    /* ── DRAWER OVERLAY ────────────────────────────────────────── */
    .sunny-drawer-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.55);
        backdrop-filter: blur(6px);
        -webkit-backdrop-filter: blur(6px);
        opacity: 0;
        pointer-events: none;
        transition: opacity var(--t-base) var(--ease);
        z-index: 60;
    }
    .sunny-drawer-overlay.active {
        opacity: 1;
        pointer-events: auto;
    }

    /* ── DRAWERS ───────────────────────────────────────────────── */
    .sunny-drawer {
        position: fixed;
        top: 0; right: 0;
        height: 100vh; height: 100dvh;
        width: min(440px, 100vw);
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border-left: 1px solid var(--glass-border);
        box-shadow: var(--shadow-lg);
        transform: translateX(105%);
        transition: transform var(--t-slow) var(--ease);
        z-index: 70;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .sunny-drawer.open { transform: translateX(0); }

    .sunny-drawer-handle {
        display: none;
        width: 40px; height: 4px;
        background: var(--surface-3);
        border-radius: 2px;
        margin: 8px auto 0;
        flex-shrink: 0;
    }

    .sunny-drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 18px 22px;
        border-bottom: 1px solid var(--glass-border);
        flex-shrink: 0;
    }
    .sunny-drawer-header h4 {
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: var(--text-main);
        letter-spacing: -0.01em;
    }
    .sunny-drawer-close {
        width: 34px; height: 34px;
        border: none;
        background: var(--surface-2);
        color: var(--text-muted);
        border-radius: 50%;
        cursor: pointer;
        font-size: 14px;
        transition: all var(--t-fast);
    }
    .sunny-drawer-close:hover {
        background: var(--surface-3);
        color: var(--text-main);
        transform: rotate(90deg);
    }

    .sunny-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px 22px 28px;
        scrollbar-width: thin;
        scrollbar-color: var(--surface-3) transparent;
    }
    .sunny-drawer-body::-webkit-scrollbar { width: 8px; }
    .sunny-drawer-body::-webkit-scrollbar-thumb { background: var(--surface-3); border-radius: 4px; }

    /* ── DRAWER : ANALYSE ──────────────────────────────────────── */
    .analyse-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
        gap: 12px;
    }
    .analyse-field { display: flex; flex-direction: column; gap: 6px; }
    .analyse-field label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }
    .analyse-field input,
    .analyse-field select,
    .product-form-row input,
    .product-form-row select,
    .product-form-row textarea {
        padding: 10px 12px;
        background: var(--bg-input);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-sm);
        color: var(--text-main);
        font-family: inherit;
        font-size: 14px;
        transition: all var(--t-fast);
    }
    .analyse-field input:focus,
    .product-form-row input:focus,
    .product-form-row select:focus,
    .product-form-row textarea:focus {
        outline: none;
        border-color: var(--gold);
        box-shadow: 0 0 0 3px var(--gold-dim);
    }

    .analyse-actions {
        margin-top: 18px;
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .analyse-submit-btn {
        padding: 12px 20px;
        background: var(--user-grad);
        color: #1a1410;
        border: none;
        border-radius: var(--radius);
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: var(--shadow-gold);
        transition: all var(--t-fast) var(--ease);
    }
    .analyse-submit-btn:hover { transform: translateY(-2px); box-shadow: 0 12px 28px var(--gold-glow); }
    .analyse-submit-btn:active { transform: translateY(0); }

    .analyse-history { margin-top: 28px; }
    .analyse-history h5 {
        margin: 0 0 12px;
        font-size: 13px;
        font-weight: 700;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }
    .analyse-history-list { display: flex; flex-direction: column; gap: 8px; }
    .analyse-history-item {
        padding: 12px 14px;
        background: var(--surface-1);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        font-size: 13px;
        color: var(--text-main);
        transition: all var(--t-fast);
    }
    .analyse-history-item:hover { background: var(--surface-2); border-color: var(--gold-border); }
    .analyse-history-item-header {
        display: flex; justify-content: space-between; align-items: center;
        margin-bottom: 8px;
    }
    .analyse-history-status {
        padding: 2px 8px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .analyse-history-status.excellente { background: rgba(46,204,113,0.18); color: #2ecc71; }
    .analyse-history-status.correcte   { background: rgba(243,156,18,0.18); color: #f39c12; }
    .analyse-history-status.corriger   { background: rgba(230,126,34,0.18); color: #e67e22; }
    .analyse-history-status.critique   { background: rgba(231,76,60,0.18); color: #e74c3c; }
    .analyse-history-values { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 6px; }
    .analyse-pill {
        padding: 3px 9px;
        background: var(--surface-2);
        border-radius: 999px;
        font-size: 11.5px;
        color: var(--text-muted);
    }
    .analyse-history-actions { display: flex; gap: 6px; margin-top: 8px; }
    .btn-history-toggle, .btn-history-discuss, .btn-discuss {
        padding: 6px 12px;
        background: var(--surface-2);
        border: 1px solid var(--glass-border);
        border-radius: 999px;
        color: var(--text-main);
        font-family: inherit;
        font-size: 12px;
        cursor: pointer;
        transition: all var(--t-fast);
    }
    .btn-history-toggle:hover, .btn-history-discuss:hover, .btn-discuss:hover {
        background: var(--gold-dim); color: var(--gold-light); border-color: var(--gold-border);
    }
    .analyse-history-diag {
        max-height: 0;
        overflow: hidden;
        transition: max-height var(--t-base) var(--ease);
        font-size: 12.5px;
        color: var(--text-muted);
    }
    .analyse-history-diag.expanded {
        max-height: 600px;
        margin-top: 8px;
        padding-top: 8px;
        border-top: 1px solid var(--glass-border);
    }
    .diag-line { padding: 4px 0; }
    .diag-emoji { margin-right: 4px; }

    /* ── DRAWER : PRODUITS ─────────────────────────────────────── */
    .sunny-products-list { display: flex; flex-direction: column; gap: 10px; }
    .sunny-products-empty {
        padding: 28px 16px;
        text-align: center;
        color: var(--text-muted);
        font-size: 13px;
        background: var(--surface-1);
        border-radius: var(--radius);
    }
    .sunny-product-item {
        padding: 12px 14px;
        background: var(--surface-1);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        display: flex;
        gap: 12px;
        align-items: center;
        transition: all var(--t-fast);
    }
    .sunny-product-item:hover { background: var(--surface-2); border-color: var(--gold-border); }
    .product-info { flex: 1; min-width: 0; }
    .product-cat {
        display: inline-block;
        padding: 2px 8px;
        background: var(--gold-dim);
        color: var(--gold-light);
        border-radius: 999px;
        font-size: 10.5px;
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 4px;
    }
    .product-brand { font-size: 11.5px; color: var(--text-muted); }
    .product-name { font-size: 13.5px; font-weight: 600; color: var(--text-main); margin-top: 2px; }
    .product-controls { display: flex; gap: 4px; align-items: center; flex-shrink: 0; }
    .product-qty, .product-unit {
        width: 64px;
        padding: 6px 8px;
        background: var(--bg-input);
        border: 1px solid var(--glass-border);
        border-radius: 6px;
        color: var(--text-main);
        font-size: 12px;
    }
    .product-unit { width: 70px; }
    .save-btn, .del-btn {
        width: 30px; height: 30px;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        font-size: 13px;
        transition: all var(--t-fast);
    }
    .save-btn { background: var(--gold-dim); color: var(--gold-light); }
    .save-btn:hover { background: var(--gold); color: #1a1410; }
    .del-btn { background: var(--surface-2); color: var(--text-muted); }
    .del-btn:hover { background: rgba(255,94,94,0.2); color: #ff8585; }
    .photo-link { color: var(--gold-light); font-size: 11px; text-decoration: underline; margin-right: 6px; }

    .sunny-product-add-form {
        margin-top: 18px;
        padding: 14px;
        background: var(--surface-1);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .product-form-row { display: flex; gap: 8px; flex-wrap: wrap; }
    .product-form-row > * { min-width: 0; }
    .btn-add-product {
        padding: 10px;
        background: var(--user-grad);
        color: #1a1410;
        border: none;
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 13px;
        font-weight: 700;
        cursor: pointer;
        transition: all var(--t-fast);
    }
    .btn-add-product:hover { transform: translateY(-1px); box-shadow: var(--shadow-gold); }

    /* ── DRAWER : OPTIONS ──────────────────────────────────────── */
    .data-options-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 10px;
    }
    .data-option-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 14px;
        background: var(--surface-1);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius);
        cursor: pointer;
        transition: all var(--t-fast) var(--ease);
        font-size: 13.5px;
        color: var(--text-main);
    }
    .data-option-item:hover { background: var(--surface-2); border-color: var(--gold-border); }
    .data-option-item input[type="checkbox"] {
        width: 18px; height: 18px;
        accent-color: var(--gold);
        cursor: pointer;
    }
    .data-option-item:has(input:checked) {
        background: var(--gold-dim);
        border-color: var(--gold-border);
    }

    /* ── DRAWER : THREADS ──────────────────────────────────────── */
    .sunny-threads-header-bar { margin-bottom: 14px; }
    .sunny-new-thread-btn {
        width: 100%;
        padding: 12px;
        background: var(--user-grad);
        color: #1a1410;
        border: none;
        border-radius: var(--radius);
        font-family: inherit;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        box-shadow: var(--shadow-gold);
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-new-thread-btn:hover { transform: translateY(-2px); box-shadow: 0 10px 24px var(--gold-glow); }

    .sunny-threads-list { display: flex; flex-direction: column; gap: 6px; }
    .sunny-threads-empty {
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }
    .sunny-threads-empty-icon { font-size: 40px; margin-bottom: 10px; opacity: 0.6; }
    .sunny-threads-empty-text { font-size: 13.5px; line-height: 1.6; }

    .sunny-thread-item {
        padding: 12px 14px;
        background: var(--surface-1);
        border: 1px solid transparent;
        border-radius: var(--radius);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 10px;
        transition: all var(--t-fast) var(--ease);
    }
    .sunny-thread-item:hover { background: var(--surface-2); border-color: var(--glass-border); }
    .sunny-thread-item.active {
        background: var(--gold-dim);
        border-color: var(--gold-border);
        color: var(--gold-light);
    }
    .sunny-thread-title { font-size: 13.5px; font-weight: 500; flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .sunny-thread-meta { font-size: 11px; color: var(--text-muted); }
    .sunny-thread-del {
        width: 26px; height: 26px;
        border: none;
        background: transparent;
        color: var(--text-faint);
        border-radius: 50%;
        cursor: pointer;
        opacity: 0;
        transition: all var(--t-fast);
    }
    .sunny-thread-item:hover .sunny-thread-del { opacity: 1; }
    .sunny-thread-del:hover { background: rgba(255,94,94,0.2); color: #ff8585; }

    /* ── EMPTY STATES ──────────────────────────────────────────── */
    .sunny-empty-state {
        padding: 40px 16px;
        text-align: center;
        color: var(--text-muted);
    }
    .sunny-empty-state-icon { font-size: 44px; margin-bottom: 12px; opacity: 0.6; }
    .sunny-empty-state-text { font-size: 14px; line-height: 1.6; }

    /* ── MODAL ANALYSE ─────────────────────────────────────────── */
    .sunny-modal-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.65);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 9000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
        animation: fade-in var(--t-base) var(--ease);
    }
    .sunny-modal-backdrop.closing { animation: fade-out var(--t-base) var(--ease) forwards; }
    @keyframes fade-in  { from { opacity: 0; } to { opacity: 1; } }
    @keyframes fade-out { from { opacity: 1; } to { opacity: 0; } }

    .sunny-modal {
        background: var(--bg-card);
        border: 1px solid var(--glass-border);
        border-radius: var(--radius-lg);
        width: 100%;
        max-width: 560px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: var(--shadow-lg);
        animation: modal-in var(--t-slow) var(--ease-spring);
    }
    @keyframes modal-in {
        from { opacity: 0; transform: scale(0.92) translateY(20px); }
        to   { opacity: 1; transform: scale(1) translateY(0); }
    }
    .sunny-modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--glass-border);
        display: flex; justify-content: space-between; align-items: center;
    }
    .sunny-modal-body { padding: 20px 24px; overflow-y: auto; flex: 1; }
    .sunny-modal-footer { padding: 16px 24px; border-top: 1px solid var(--glass-border); display: flex; justify-content: flex-end; gap: 10px; }
    .sunny-modal-close, .btn-close-modal {
        width: 32px; height: 32px; border: none; border-radius: 50%;
        background: var(--surface-2); color: var(--text-muted); cursor: pointer;
        transition: all var(--t-fast);
    }
    .btn-close-modal { width: auto; height: auto; padding: 8px 18px; border-radius: 999px; font-size: 13px; }
    .sunny-modal-close:hover, .btn-close-modal:hover { background: var(--surface-3); color: var(--text-main); }
    .modal-alertes { display: flex; flex-direction: column; gap: 8px; margin-top: 14px; }
    .modal-alerte { padding: 10px 14px; border-radius: var(--radius); display: flex; gap: 10px; align-items: flex-start; font-size: 13.5px; border-left: 3px solid; }
    .modal-alerte.faible  { border-color: #f5d76e; background: rgba(245,215,110,0.08); color: #f5d76e; }
    .modal-alerte.moyenne { border-color: #ff9f43; background: rgba(255,159,67,0.10); color: #ffb976; }
    .modal-alerte.haute   { border-color: #ff5e5e; background: rgba(255,94,94,0.12); color: #ff8585; }

    /* ── TOASTS ────────────────────────────────────────────────── */
    .sunny-toast-container {
        position: fixed;
        top: 20px; right: 20px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        z-index: 10000;
        pointer-events: none;
    }
    .sunny-toast {
        background: var(--glass-bg);
        backdrop-filter: var(--glass-blur);
        -webkit-backdrop-filter: var(--glass-blur);
        border: 1px solid var(--glass-border);
        border-left: 3px solid var(--gold);
        color: var(--text-main);
        padding: 12px 18px;
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        font-size: 13.5px;
        font-weight: 500;
        min-width: 240px;
        max-width: 360px;
        pointer-events: auto;
        animation: toast-in var(--t-slow) var(--ease-spring);
    }
    .sunny-toast.success { border-left-color: #2ecc71; }
    .sunny-toast.error   { border-left-color: #ff5e5e; }
    .sunny-toast.info    { border-left-color: var(--gold); }
    .sunny-toast.warning { border-left-color: #ff9f43; }
    .sunny-toast.hiding  { animation: toast-out var(--t-base) var(--ease) forwards; }
    @keyframes toast-in {
        from { opacity: 0; transform: translateX(40px) scale(0.9); }
        to   { opacity: 1; transform: translateX(0) scale(1); }
    }
    @keyframes toast-out {
        to { opacity: 0; transform: translateX(40px); }
    }

    .sunny-loading-dots::after {
        content: '...';
        display: inline-block;
        animation: loading-dots 1.4s infinite;
    }
    @keyframes loading-dots {
        0%, 20%   { content: '.'; }
        40%       { content: '..'; }
        60%, 100% { content: '...'; }
    }

    /* ════════════════════════════════════════════════════════════
       RESPONSIVE — TABLETTE & MOBILE
       ════════════════════════════════════════════════════════════ */
    @media (max-width: 900px) {
        .sunny-chat-wrapper {
            grid-template-columns: 1fr;
            grid-template-areas:
                "header"
                "main";
        }
        .sunny-sidebar { display: none; }
    }

    @media (max-width: 720px) {
        :root { --header-h: 58px; }
        .sunny-chat-wrapper {
            grid-template-rows: var(--header-h) 1fr var(--mobilenav-h);
            grid-template-areas:
                "header"
                "main"
                "mobilenav";
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        .sunny-chat-header { padding: 0 14px; gap: 10px; }
        .sunny-avatar { width: 36px; height: 36px; font-size: 18px; }
        .sunny-header-info h3 { font-size: 14px; }
        .sunny-header-actions { display: none; }

        .sunny-mobile-nav {
            display: flex !important;
            position: relative;
            grid-area: mobilenav;
            bottom: auto; left: auto; right: auto;
        }

        .sunny-chat-messages { padding: 16px 14px 12px; gap: 10px; }
        .chat-bubble { max-width: 88%; font-size: 14px; }
        .chat-bubble.sunny .sunny-rich-section { padding: 9px 10px; }
        .chat-bubble.sunny .sunny-rich-link { width: 100%; justify-content: center; }
        .sunny-chat-input-area { padding: 8px 12px 12px; }

        .sunny-drawer {
            top: auto;
            bottom: 0;
            right: 0; left: 0;
            width: 100%;
            height: min(86vh, 86dvh);
            border-left: none;
            border-top: 1px solid var(--glass-border);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            transform: translateY(105%);
        }
        .sunny-drawer.open { transform: translateY(0); }
        .sunny-drawer-handle { display: block; }
        .sunny-drawer-header { padding: 12px 18px 14px; }

        .sunny-suggestions { padding-bottom: 8px; }
        .sunny-suggestion-pill { font-size: 12px; padding: 6px 12px; }

        .sunny-toast-container { top: 12px; left: 12px; right: 12px; }
        .sunny-toast { min-width: 0; max-width: none; }
    }

    /* ════════════════════════════════════════════════════════════
       PREFERS-REDUCED-MOTION
       ════════════════════════════════════════════════════════════ */
    @media (prefers-reduced-motion: reduce) {
        .sunny-chat-wrapper *,
        .sunny-chat-wrapper *::before,
        .sunny-chat-wrapper *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
            scroll-behavior: auto !important;
        }
        .sunny-chat-wrapper::before,
        .sunny-chat-wrapper::after { display: none; }
    }

    /* ── ACCESSIBILITY ─────────────────────────────────────────── */
    .sunny-chat-wrapper button:focus-visible,
    .sunny-chat-wrapper select:focus-visible,
    .sunny-chat-wrapper input:focus-visible,
    .sunny-chat-wrapper textarea:focus-visible {
        outline: 2px solid var(--gold-light);
        outline-offset: 2px;
    }
    .sunny-chat-wrapper button { -webkit-tap-highlight-color: transparent; }

    /* Neutralise les CSS de thème WP qui pourraient casser */
    .sunny-chat-wrapper .select2-container { display: none !important; }
    body.sunny-chat-active { overflow: hidden; }

    /* ── FIX : neutralise les styles de boutons héritées du thème WP ── */
    .sunny-chat-wrapper .sunny-sidebar button,
    .sunny-chat-wrapper .sunny-mobile-nav button {
        background-image: none !important;
        text-transform: none !important;
        letter-spacing: normal !important;
        box-shadow: none;
        min-width: 0 !important;
        min-height: 0 !important;
        line-height: 1 !important;
    }
    .sunny-chat-wrapper .sunny-sidebar-btn {
        background: var(--user-grad) !important;
        box-shadow: var(--shadow-gold);
        color: #1a1410 !important;
        font-size: 0 !important;
        overflow: hidden;
        white-space: nowrap;
        padding: 0 !important;
    }
    .sunny-chat-wrapper .sunny-sidebar.expanded .sunny-sidebar-btn {
        font-size: 13px !important;
        padding: 0 14px !important;
    }
    .sunny-chat-wrapper .sunny-sidebar-link {
        background: transparent !important;
        color: var(--text-muted) !important;
        font-size: 0 !important;
        overflow: hidden;
        white-space: nowrap;
        padding: 0 !important;
    }
    .sunny-chat-wrapper .sunny-sidebar.expanded .sunny-sidebar-link {
        font-size: 13px !important;
        padding: 0 14px !important;
    }
    .sunny-chat-wrapper .sunny-sidebar-link:hover {
        background: var(--surface-2) !important;
        color: var(--gold-light) !important;
    }
    /* Ré-affiche uniquement les icônes Material Symbols */
    .sunny-chat-wrapper .sunny-sidebar-btn .material-symbols-outlined,
    .sunny-chat-wrapper .sunny-sidebar-link .material-symbols-outlined {
        font-size: 24px !important;
    }
    .sunny-chat-wrapper .sunny-sidebar-btn .material-symbols-outlined { font-size: 26px !important; }
    /* Labels visibles quand sidebar expanded */
    .sunny-chat-wrapper .sunny-sidebar .sidebar-label { display: none !important; color: var(--text-muted) !important; }
    .sunny-chat-wrapper .sunny-sidebar.expanded .sidebar-label { display: inline !important; color: inherit !important; }
    .sunny-chat-wrapper .sunny-sidebar-btn .sidebar-label { color: #1a1410 !important; }
    .sunny-chat-wrapper .sunny-sidebar-logout .sidebar-label { color: var(--text-muted) !important; }
    .sunny-chat-wrapper .sunny-sidebar.expanded .sunny-sidebar-logout { font-size: 13px !important; padding: 0 14px !important; }
    /* Toggle button visible */
    .sunny-chat-wrapper .sunny-sidebar-toggle { color: var(--text-main) !important; background: var(--surface-2) !important; }
    .sunny-chat-wrapper .sunny-sidebar-toggle:hover { color: var(--gold-light) !important; background: var(--surface-3) !important; }
    /* Section labels visibles quand expanded */
    .sunny-chat-wrapper .sunny-sidebar-section-label { display: none !important; color: var(--text-faint) !important; }
    .sunny-chat-wrapper .sunny-sidebar.expanded .sunny-sidebar-section-label { display: block !important; }

    /* Mobile bottom nav : neutralise styles thème */
    .sunny-chat-wrapper .sunny-mobile-nav-btn {
        background: transparent !important;
        border-radius: 12px !important;
        padding: 6px 8px !important;
        text-transform: none !important;
        font-weight: 500 !important;
        color: var(--text-muted) !important;
    }
    .sunny-chat-wrapper .sunny-mobile-nav-btn:hover {
        color: var(--text-main) !important;
    }

    /* ── FIX : <option> lisibles dans tous les <select> ── */
    .sunny-chat-wrapper select option,
    .sunny-chat-wrapper select optgroup {
        background: #1a1d24 !important;
        color: #f5f5f5 !important;
    }

    /* ── FIX : actions thread (renommer / supprimer) ── */
    .sunny-thread-item { position: relative; }
    .sunny-thread-icon {
        flex-shrink: 0;
        width: 28px; height: 28px;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 16px;
        background: var(--surface-2);
        border-radius: 50%;
    }
    .sunny-thread-info { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .sunny-thread-preview {
        font-size: 11.5px;
        color: var(--text-muted);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .sunny-thread-meta { display: flex; gap: 8px; align-items: center; font-size: 11px; color: var(--text-muted); }
    .sunny-thread-badge {
        padding: 1px 7px;
        background: var(--gold-dim);
        color: var(--gold-light);
        border-radius: 999px;
        font-size: 10px;
        font-weight: 600;
    }
    .sunny-thread-actions {
        display: flex;
        gap: 4px;
        flex-shrink: 0;
        opacity: 0;
        transition: opacity var(--t-fast);
    }
    .sunny-thread-item:hover .sunny-thread-actions,
    .sunny-thread-item.active .sunny-thread-actions { opacity: 1; }
    .sunny-thread-action-btn {
        width: 28px; height: 28px;
        border: none;
        background: var(--surface-2) !important;
        color: var(--text-muted) !important;
        border-radius: 50%;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 13px !important;
        line-height: 1 !important;
        padding: 0 !important;
        min-width: 0 !important;
        transition: all var(--t-fast);
    }
    .sunny-thread-action-btn::before { content: attr(data-emoji); }
    .sunny-thread-action-btn:hover { background: var(--surface-3) !important; color: var(--text-main) !important; }
    .sunny-thread-action-btn.delete:hover { background: rgba(255,94,94,0.2) !important; color: #ff8585 !important; }

    /* ── FOLDERS ── */
    .sunny-folders-section { display: flex; flex-direction: column; gap: 8px; margin-bottom: 14px; }
    .sunny-folders-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 4px 2px; font-size: 11px; font-weight: 600;
        text-transform: uppercase; letter-spacing: 0.6px; color: var(--text-muted);
    }
    .sunny-new-folder-btn {
        background: transparent; border: 1px dashed var(--glass-border);
        color: var(--text-muted); border-radius: 8px; padding: 4px 10px;
        font-size: 11px; cursor: pointer; transition: all .2s ease;
        display: inline-flex; align-items: center; gap: 4px;
    }
    .sunny-new-folder-btn:hover { color: var(--gold); border-color: var(--gold); background: var(--gold-dim); }

    .sunny-folder-item {
        background: var(--surface-1); border: 1px solid var(--glass-border);
        border-radius: 12px; overflow: hidden; transition: border-color .2s ease;
    }
    .sunny-folder-item:hover { border-color: var(--gold-dim); }
    .sunny-folder-head {
        display: flex; align-items: center; gap: 8px; padding: 10px 12px;
        cursor: pointer; user-select: none; position: relative;
    }
    .sunny-folder-caret {
        display: inline-block; width: 14px; transition: transform .2s ease;
        color: var(--text-muted); font-size: 10px;
    }
    .sunny-folder-item.open .sunny-folder-caret { transform: rotate(90deg); }
    .sunny-folder-icon { font-size: 16px; }
    .sunny-folder-name {
        flex: 1; min-width: 0; font-size: 13.5px; font-weight: 500;
        color: var(--text-main); overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .sunny-folder-count {
        font-size: 11px; color: var(--text-muted); background: var(--surface-2);
        padding: 2px 7px; border-radius: 10px;
    }
    .sunny-folder-actions {
        display: flex; gap: 4px; opacity: 0; transition: opacity .2s ease;
    }
    .sunny-folder-item:hover .sunny-folder-actions { opacity: 1; }
    .sunny-folder-body {
        display: none; padding: 4px 8px 10px 8px;
        border-top: 1px solid var(--glass-border); background: var(--surface-0, rgba(0,0,0,0.15));
    }
    .sunny-folder-item.open .sunny-folder-body { display: flex; flex-direction: column; gap: 4px; }
    .sunny-folder-empty {
        font-size: 12px; color: var(--text-muted); text-align: center;
        padding: 8px 4px; font-style: italic;
    }
    .sunny-folder-new-thread {
        background: transparent; border: 1px dashed var(--glass-border);
        color: var(--text-muted); border-radius: 8px; padding: 6px 10px;
        font-size: 12px; cursor: pointer; transition: all .2s ease;
        margin: 4px 0 0 0; text-align: left;
    }
    .sunny-folder-new-thread:hover { color: var(--gold); border-color: var(--gold); background: var(--gold-dim); }
    .sunny-folder-item .sunny-thread-item { background: transparent; border-color: transparent; }
    .sunny-folder-item .sunny-thread-item:hover { background: var(--surface-2); border-color: var(--glass-border); }

    /* ── FIX : produits — alignement et icônes visibles ── */
    .sunny-product-item { flex-wrap: wrap; }
    .sunny-product-item .product-info { flex: 1 1 100%; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .sunny-product-item .product-controls {
        flex: 1 1 100%;
        flex-wrap: wrap;
        gap: 6px;
        justify-content: flex-end;
        align-items: center;
    }
    .sunny-chat-wrapper .save-btn,
    .sunny-chat-wrapper .del-btn {
        background: var(--surface-2) !important;
        color: var(--text-main) !important;
        font-size: 14px !important;
        line-height: 1 !important;
        padding: 0 !important;
        min-width: 30px !important;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .sunny-chat-wrapper .save-btn { background: var(--gold-dim) !important; color: var(--gold-light) !important; }
    .sunny-chat-wrapper .save-btn:hover { background: var(--gold) !important; color: #1a1410 !important; }
    .sunny-chat-wrapper .del-btn:hover { background: rgba(255,94,94,0.2) !important; color: #ff8585 !important; }
    .sunny-chat-wrapper .photo-link {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 30px; height: 30px;
        border-radius: 50%;
        background: var(--surface-2);
        text-decoration: none !important;
        font-size: 14px;
    }

    @media (min-width: 600px) {
        .sunny-product-item { flex-wrap: nowrap; }
        .sunny-product-item .product-info { flex: 1 1 auto; }
        .sunny-product-item .product-controls { flex: 0 0 auto; }
    }
    </style>


    <!-- ===== HTML ===== -->

    <!-- Toast Container -->
    <div class="sunny-toast-container" id="sunny-toast-container"></div>

    <!-- Header -->
    <header class="sunny-chat-header" role="banner">
        <div class="sunny-avatar" aria-label="Sunny Avatar">🌞</div>
        <div class="sunny-header-info">
            <h3>Sunny Chat Expert</h3>
            <div class="sunny-status-row">
                <span class="sunny-status-text" id="sunny-thread-indicator" onclick="sunnyOpenDrawer('threads')" style="cursor:pointer;">
                    💬 Nouvelle discussion
                </span>
                <?php if (count($pools) > 1) : ?>
                <div class="sunny-pool-selector-wrap">
                    <select id="sunny-pool-selector" class="no-select2 ignore-select2 wpo-select2-ignore">
                        <?php foreach ($pools as $pool) :
                            $vol = get_field('volume', $pool->ID);
                        ?>
                        <option value="<?php echo $pool->ID; ?>" <?php echo $selected_pool_id == $pool->ID ? 'selected' : ''; ?>>
                            <?php echo esc_html($pool->post_title); ?><?php echo $vol ? ' · ' . $vol . ' m³' : ''; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php else : ?>
                <span class="sunny-status-text"><?php echo $pools ? esc_html($pool_titre) . ($pool_volume ? ' · ' . $pool_volume . ' m³' : '') : 'Aucune piscine'; ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="sunny-header-actions">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="sunny-hdr-link" title="Accueil">
                <span class="material-symbols-outlined">home</span>
                Accueil
            </a>
            <a href="<?php echo esc_url(home_url('/mes-piscines/')); ?>" class="sunny-hdr-link" title="Mes piscines">
                <span class="material-symbols-outlined">pool</span>
                Mes piscines
            </a>
            <a href="<?php echo esc_url(home_url('/ajouter-ma-piscine/')); ?>" class="sunny-hdr-link" title="Ajouter piscine">
                <span class="material-symbols-outlined">add_home</span>
                Ajouter
            </a>
        </div>
    </header>

    <!-- Sidebar (Desktop) -->
    <aside class="sunny-sidebar" id="sunny-sidebar">
        <button class="sunny-sidebar-toggle" id="sunny-sidebar-toggle" onclick="sunnyToggleSidebar()" title="Agrandir le menu">
            <span class="material-symbols-outlined">chevron_right</span>
        </button>
        <button class="sunny-sidebar-btn" onclick="sunnyOpenDrawer('analyse')">
            <span class="material-symbols-outlined">add_circle</span>
            <span class="sidebar-label">Nouvelle analyse</span>
        </button>
        <div class="sunny-sidebar-divider"></div>
        <div class="sunny-sidebar-section-label">Navigation</div>
        <nav class="sunny-sidebar-nav">
            <a href="<?php echo esc_url(home_url('/')); ?>" class="sunny-sidebar-link" data-tooltip="Accueil">
                <span class="material-symbols-outlined">home</span>
                <span class="sidebar-label">Accueil</span>
            </a>
            <a href="<?php echo esc_url(home_url('/mes-piscines/')); ?>" class="sunny-sidebar-link" data-tooltip="Mes piscines">
                <span class="material-symbols-outlined">pool</span>
                <span class="sidebar-label">Mes piscines</span>
            </a>
            <a href="<?php echo esc_url(home_url('/ajouter-ma-piscine/')); ?>" class="sunny-sidebar-link" data-tooltip="Ajouter piscine">
                <span class="material-symbols-outlined">add_home</span>
                <span class="sidebar-label">Ajouter piscine</span>
            </a>
        </nav>
        <div class="sunny-sidebar-divider"></div>
        <div class="sunny-sidebar-section-label">Outils</div>
        <nav class="sunny-sidebar-nav" style="flex:0; margin-top:0;">
            <button class="sunny-sidebar-link" id="sidebar-analyse" data-tooltip="Analyse d'eau" onclick="sunnyOpenDrawer('analyse')">
                <span class="material-symbols-outlined">analytics</span>
                <span class="sidebar-label">Analyse d'eau</span>
            </button>
            <button class="sunny-sidebar-link" id="sidebar-products" data-tooltip="Mes produits" onclick="sunnyOpenDrawer('products')">
                <span class="material-symbols-outlined">water_drop</span>
                <span class="sidebar-label">Mes produits</span>
            </button>
            <button class="sunny-sidebar-link" id="sidebar-options" data-tooltip="Options" onclick="sunnyOpenDrawer('options')">
                <span class="material-symbols-outlined">settings</span>
                <span class="sidebar-label">Options</span>
            </button>
            <button class="sunny-sidebar-link" id="sidebar-threads" data-tooltip="Discussions" onclick="sunnyOpenDrawer('threads')">
                <span class="material-symbols-outlined">forum</span>
                <span class="sidebar-label">Discussions</span>
            </button>
        </nav>
        <div class="sunny-sidebar-footer">
            <button class="sunny-sidebar-link" data-tooltip="Support" onclick="sunnyToast('Support à venir', 'info')">
                <span class="material-symbols-outlined">help</span>
                <span class="sidebar-label">Support</span>
            </button>
            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="sunny-sidebar-logout" data-tooltip="Déconnexion">
                <span class="material-symbols-outlined">logout</span>
                <span class="sidebar-label">Déconnexion</span>
            </a>
        </div>
    </aside>

    <!-- Main Chat Area -->
    <main class="sunny-chat-main" style="flex:1; display:flex; flex-direction:column; overflow:hidden; margin-left:0;">
        <!-- Messages -->
        <div class="sunny-chat-messages" id="sunny-messages">
            <div class="chat-bubble system">🌞 Sunny est prêt à vous aider avec votre piscine</div>
            <div class="chat-bubble system">Envoyez une photo de bandelette pour une analyse automatique</div>
            <?php if (!$has_pool): ?>
            <div class="chat-bubble system">⚠️ <a href="<?php echo home_url('/ajouter-ma-piscine'); ?>" style="color:#eab308; text-decoration:underline;">Ajoutez votre piscine</a> pour des conseils personnalisés</div>
            <?php endif; ?>
        </div>

            <!-- FAB scroll to bottom -->
            <button class="sunny-scroll-fab" id="sunny-scroll-fab" onclick="sunnyScrollToBottom()" aria-label="Aller en bas" title="Aller en bas">
                <span class="material-symbols-outlined">keyboard_double_arrow_down</span>
            </button>

        <!-- Input Area -->
        <div class="sunny-chat-input-area">
            <!-- Quick suggestions -->
            <div class="sunny-suggestions" id="sunny-suggestions">
                <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Mon eau est verte, que faire ?')">Eau verte</button>
                <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Mon pH est trop élevé')">pH élevé</button>
                <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Planning entretien')">Planning</button>
                <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Chlore insuffisant')">Chlore faible</button>
                <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Hivernage')">Hivernage</button>
            </div>

            <!-- Image preview compact -->
            <div class="sunny-image-preview" id="sunny-image-preview-bar">
                <img id="img-preview-thumb" src="" alt="Aperçu">
                <div class="img-meta">
                    <div class="sunny-image-meta-label" id="img-preview-label">Photo jointe</div>
                    <div class="sunny-image-meta-sub" id="img-preview-sub">Tapez un message et envoyez</div>
                </div>
                <button class="sunny-image-remove" onclick="sunnyRemoveImage()" title="Retirer">✕</button>
            </div>

            <!-- Hidden file input -->
            <input type="file" id="sunny-image-file" class="sunny-file-input" accept="image/*" capture="environment" onchange="sunnyLoadImage(this)">

            <!-- Unified input row -->
            <div class="sunny-input-unified">
                <!-- Attachment button -->
                <button class="sunny-attach-btn" id="sunny-attach-btn" onclick="document.getElementById('sunny-image-file').click();" title="Ajouter une photo">
                    <span class="material-symbols-outlined">attach_file</span>
                </button>

                <textarea id="sunny-input"
                    placeholder="Demandez à Sunny..."
                    rows="1"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sunnySend();}"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>

                <button class="sunny-send-btn" id="sunny-send" onclick="sunnySend()" title="Envoyer">
                    <span class="material-symbols-outlined">send</span>
                </button>
            </div>
        </div>
    </main>

    <!-- Mobile Bottom Navigation -->
    <nav class="sunny-mobile-nav">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="sunny-mobile-nav-btn">
            <span class="material-symbols-outlined">home</span>
            Accueil
        </a>
        <a href="<?php echo esc_url(home_url('/mes-piscines/')); ?>" class="sunny-mobile-nav-btn">
            <span class="material-symbols-outlined">pool</span>
            Piscines
        </a>
        <button class="sunny-mobile-nav-btn" onclick="sunnyOpenDrawer('threads')">
            <span class="material-symbols-outlined">forum</span>
            Chat
        </button>
        <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="sunny-mobile-nav-btn" style="color:var(--text-faint);">
            <span class="material-symbols-outlined">logout</span>
            Sortir
        </a>
    </nav>

    <!-- ── DRAWER OVERLAY ── -->
    <div class="sunny-drawer-overlay" id="sunny-drawer-overlay" onclick="sunnyCloseDrawer()"></div>

    <!-- ── MODAL RÉSULTAT ANALYSE ── -->
    <div id="sunny-analyse-modal-root"></div>

    <!-- ── DRAWER : ANALYSE ── -->
    <div class="sunny-drawer" id="drawer-analyse">
        <div class="sunny-drawer-handle"></div>
        <div class="sunny-drawer-header">
            <h4>📊 Mesures d'eau</h4>
            <button class="sunny-drawer-close" onclick="sunnyCloseDrawer()">✕</button>
        </div>
        <div class="sunny-drawer-body">
            <div class="analyse-grid">
                <div class="analyse-field"><label>pH</label><input type="number" id="ana-ph" step="0.1" min="0" max="14" placeholder="ex: 7.4"></div>
                <div class="analyse-field"><label>Chlore (mg/L)</label><input type="number" id="ana-chlore" step="0.1" min="0" placeholder="ex: 1.5"></div>
                <div class="analyse-field"><label>TAC (mg/L)</label><input type="number" id="ana-tac" step="1" min="0" placeholder="ex: 100"></div>
                <div class="analyse-field"><label>Stabilisant (mg/L)</label><input type="number" id="ana-stabilisant" step="1" min="0" placeholder="ex: 30"></div>
                <div class="analyse-field"><label>Température (°C)</label><input type="number" id="ana-temperature" step="0.5" min="0" max="45" placeholder="ex: 26"></div>
            </div>
            <div class="analyse-actions">
                <p style="color:var(--text-muted);font-size:0.78em;margin:0;">Ces valeurs sont aussi stockées dans l'historique d'analyse.</p>
                <button type="button" class="analyse-submit-btn" onclick="sunnySubmitWaterAnalyse()">Analyser mon eau</button>
            </div>
            <div class="analyse-history">
                <h5>Historique des analyses</h5>
                <div id="sunny-analyse-history-list" class="analyse-history-list">
                    <div class="analyse-history-item">Chargement...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── DRAWER : PRODUITS ── -->
    <div class="sunny-drawer" id="drawer-products">
        <div class="sunny-drawer-handle"></div>
        <div class="sunny-drawer-header">
            <h4>🧴 Mes produits d'entretien</h4>
            <button class="sunny-drawer-close" onclick="sunnyCloseDrawer()">✕</button>
        </div>
        <div class="sunny-drawer-body">
            <div id="sunny-products-list" class="sunny-products-list">
                <div class="sunny-products-empty">Chargement des produits...</div>
            </div>
            <div class="sunny-product-add-form">
                <div class="product-form-row">
                    <select id="product-categorie" class="no-select2 ignore-select2" style="flex:1.5">
                        <option value="">Catégorie...</option>
                        <option value="chlore_choc">Chlore choc</option>
                        <option value="chlore_lent">Chlore lent</option>
                        <option value="ph_plus">pH +</option>
                        <option value="ph_moins">pH -</option>
                        <option value="anti_algues">Anti-algues</option>
                        <option value="clarifiant">Clarifiant</option>
                        <option value="floculant">Floculant</option>
                        <option value="sequestrant_calcaire">Séquestrant calcaire</option>
                        <option value="sequestrant_metaux">Séquestrant métaux</option>
                    </select>
                    <input type="text" id="product-marque" placeholder="Marque (ex: HTH)" style="flex:1">
                    <input type="text" id="product-nom" placeholder="Nom du produit" style="flex:2">
                </div>
                <div class="product-form-row">
                    <input type="number" id="product-quantity" placeholder="Qté" step="0.1" min="0" style="width:80px">
                    <select id="product-unit" class="no-select2 ignore-select2" style="width:90px">
                        <option value="L">L</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                        <option value="unités">unités</option>
                    </select>
                    <button class="btn-add-product" onclick="sunnyAddProduct()" style="flex:1">➕ Ajouter</button>
                </div>
                <div class="product-form-row">
                    <input type="file" id="product-photo-face"   accept="image/*" capture="environment" style="flex:1">
                    <input type="file" id="product-photo-notice" accept="image/*" style="flex:1">
                </div>
                <div class="product-form-row">
                    <textarea id="product-commentaire" placeholder="Commentaire (optionnel)..." rows="2" style="flex:1"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- ── DRAWER : OPTIONS ── -->
    <div class="sunny-drawer" id="drawer-options">
        <div class="sunny-drawer-handle"></div>
        <div class="sunny-drawer-header">
            <h4>⚙️ Données à inclure</h4>
            <button class="sunny-drawer-close" onclick="sunnyCloseDrawer()">✕</button>
        </div>
        <div class="sunny-drawer-body">
            <div class="data-options-grid">
                <label class="data-option-item"><input type="checkbox" id="opt-meteo" checked><span>🌤️ Météo locale</span></label>
                <label class="data-option-item"><input type="checkbox" id="opt-historique" checked><span>📜 Historique</span></label>
                <label class="data-option-item"><input type="checkbox" id="opt-produits"><span>🧴 Mes produits</span></label>
                <label class="data-option-item"><input type="checkbox" id="opt-alertes" checked><span>⚠️ Alertes auto</span></label>
                <label class="data-option-item"><input type="checkbox" id="opt-planning"><span>📅 Planning</span></label>
                <label class="data-option-item"><input type="checkbox" id="opt-coordonnees" checked><span>📍 Coordonnées GPS</span></label>
            </div>
        </div>
    </div>

    <!-- ── DRAWER : DISCUSSIONS (THREADS) ── -->
    <div class="sunny-drawer" id="drawer-threads">
        <div class="sunny-drawer-handle"></div>
        <div class="sunny-drawer-header">
            <h4>💬 Mes discussions</h4>
            <button class="sunny-drawer-close" onclick="sunnyCloseDrawer()">✕</button>
        </div>
        <div class="sunny-drawer-body">
            <div class="sunny-threads-header-bar" style="display:flex; gap:8px; flex-wrap:wrap;">
                <button class="sunny-new-thread-btn" id="sunny-new-thread-btn" onclick="sunnyCreateThread()">
                    ➕ Nouvelle discussion
                </button>
                <button class="sunny-new-thread-btn" id="sunny-new-folder-btn" onclick="sunnyCreateFolder()" style="background: var(--surface-2); border:1px solid var(--glass-border);">
                    📁 Nouveau dossier
                </button>
            </div>
            <div id="sunny-folders-list" class="sunny-folders-section"></div>
            <div class="sunny-folders-header"><span>Discussions</span></div>
            <div id="sunny-threads-list" class="sunny-threads-list">
                <div class="sunny-threads-empty">
                    <div class="sunny-threads-empty-icon">💬</div>
                    <div class="sunny-threads-empty-text">Aucune discussion.<br>Créez-en une pour commencer !</div>
                </div>
            </div>
        </div>
    </div>

    </div><!-- .sunny-chat-wrapper -->

    <!-- ===== JAVASCRIPT ===== -->
    <script>
    (function($) {
        "use strict";

        $(document).ready(function() {
            const API_URL      = '<?php echo $api_url; ?>';
            const CALLBACK_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/chat-callback')); ?>';
            const NONCE        = '<?php echo $nonce; ?>';
            const HISTORY_URL  = '<?php echo esc_url(rest_url('sunny-pool/v1/chat/history')); ?>';
            const PRODUCTS_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/pool')); ?>';
            const THREADS_URL  = '<?php echo esc_url(rest_url('sunny-pool/v1/chat/threads')); ?>';
            const FOLDERS_URL  = '<?php echo esc_url(rest_url('sunny-pool/v1/folders')); ?>';
            const FOLDER_URL   = '<?php echo esc_url(rest_url('sunny-pool/v1/folder')); ?>';
            const FOLDER_THREADS_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/threads/folder')); ?>';
            const ANALYSE_URL  = '<?php echo esc_url(rest_url('sunny-pool/v1/analyse')); ?>';
            const ANALYSE_HISTORY_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/analyse/history')); ?>';

            let currentPoolId    = <?php echo $selected_pool_id; ?>;
            let currentThreadId  = null;
            let imageBase64      = null;
            let currentImageType = 'general';
            let isLoading        = false;
            let currentDrawer    = null;

            const msgsContainer = document.getElementById('sunny-messages');

            // ── SIDEBAR TOGGLE (ChatGPT-style) ──────────────────────
            window.sunnyToggleSidebar = function() {
                const sidebar = document.getElementById('sunny-sidebar');
                if (!sidebar) return;
                sidebar.classList.toggle('expanded');
                // Save preference
                try { localStorage.setItem('sunny-sidebar-expanded', sidebar.classList.contains('expanded')); } catch(e) {}
            };
            // Restore sidebar state
            try {
                if (localStorage.getItem('sunny-sidebar-expanded') === 'true') {
                    const sidebar = document.getElementById('sunny-sidebar');
                    if (sidebar) sidebar.classList.add('expanded');
                }
            } catch(e) {}

            // --- Disable Select2 ---
            function killSelect2() {
                if (typeof $.fn.select2 !== 'undefined') {
                    $('#sunny-pool-selector, #product-categorie, #product-unit').each(function() {
                        var $s = $(this);
                        if ($s.hasClass('select2-hidden-accessible')) {
                            try { $s.select2('destroy'); $s.removeClass('select2-hidden-accessible'); } catch (e) {}
                        }
                    });
                }
            }
            // Some themes initialize async
            setTimeout(killSelect2, 50);
            setTimeout(killSelect2, 400);
            setTimeout(killSelect2, 1200);

            // ── DRAWER SYSTEM ────────────────────────────────────────
            window.sunnyOpenDrawer = function(name) {
                if (currentDrawer) sunnyCloseDrawer(true);
                const drawer  = document.getElementById('drawer-' + name);
                const overlay = document.getElementById('sunny-drawer-overlay');
                if (!drawer) return;

                // Mark header btn active
                document.querySelectorAll('.sunny-hdr-btn').forEach(b => b.classList.remove('active'));
                const hdrBtn = document.getElementById('hdr-' + name + '-btn');
                if (hdrBtn) hdrBtn.classList.add('active');

                overlay.classList.add('active');
                drawer.classList.add('open');
                currentDrawer = name;

                if (name === 'products') loadProducts();
                if (name === 'threads') loadThreads();
                if (name === 'analyse') loadAnalyseHistory();
            };

            window.sunnyCloseDrawer = function(silent) {
                if (!currentDrawer) return;
                const drawer  = document.getElementById('drawer-' + currentDrawer);
                const overlay = document.getElementById('sunny-drawer-overlay');
                if (drawer)  drawer.classList.remove('open');
                overlay.classList.remove('active');
                document.querySelectorAll('.sunny-hdr-btn').forEach(b => b.classList.remove('active'));
                currentDrawer = null;
            };

            // ── HISTORY ─────────────────────────────────────────────
            function loadChatHistory(poolId, threadId) {
                if (!msgsContainer) return;
                msgsContainer.innerHTML = '<div class="chat-bubble system">Chargement...</div>';

                let url = `${HISTORY_URL}?pool_id=${poolId}`;
                if (threadId) url += `&thread_id=${threadId}`;

                $.ajax({
                    url: url,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        msgsContainer.innerHTML = '';
                        if (data.success && data.data.length > 0) {
                            data.data.reverse().forEach(function(msg) {
                                if (msg.message && msg.message !== '[callback]') {
                                    appendBubble(msg.message, 'user');
                                }
                                if (msg.response) {
                                    appendBubble(msg.response, 'sunny');
                                }
                            });
                        } else {
                            appendBubble('Bonjour ! Je suis Sunny, votre assistant piscine. Posez-moi vos questions 🏊', 'system');
                            <?php if (!$has_pool): ?>
                            appendBubble('⚠️ Vous n\'avez pas encore enregistré de piscine. <a href="<?php echo home_url('/ajouter-ma-piscine'); ?>" style="color:#ffd700;">Ajoutez-la ici</a> pour des conseils personnalisés.', 'system');
                            <?php endif; ?>
                        }
                    },
                    error: function() {
                        msgsContainer.innerHTML = '';
                        appendBubble('Bonjour ! Je suis Sunny, votre assistant piscine 🏊', 'system');
                    }
                });
            }

            // ── THREADS MANAGEMENT ────────────────────────────────────
            function updateThreadIndicator(title) {
                const indicator = document.getElementById('sunny-thread-indicator');
                if (indicator) {
                    indicator.textContent = '💬 ' + (title || 'Nouvelle discussion');
                    indicator.title = title || 'Discussion en cours';
                }
            }

            function loadThreads(andSelectFirst) {
                const list = document.getElementById('sunny-threads-list');
                if (!list) return;
                list.innerHTML = '<div class="sunny-threads-empty"><div class="sunny-threads-empty-icon">⏳</div><div class="sunny-threads-empty-text">Chargement...</div></div>';

                loadFolders();
                $.ajax({
                    url: `${THREADS_URL}?pool_id=${currentPoolId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success && data.data.length > 0) {
                            renderThreadsList(data.data);
                            if (andSelectFirst || !currentThreadId) {
                                switchToThread(data.data[0].id, data.data[0].title);
                            }
                        } else {
                            list.innerHTML = '<div class="sunny-threads-empty"><div class="sunny-threads-empty-icon">💬</div><div class="sunny-threads-empty-text">Aucune discussion.<br>Créez-en une pour commencer !</div></div>';
                            if (andSelectFirst && !currentThreadId) sunnyCreateThread();
                        }
                    },
                    error: function() {
                        list.innerHTML = '<div class="sunny-threads-empty"><div class="sunny-threads-empty-icon">❌</div><div class="sunny-threads-empty-text">Erreur de chargement</div></div>';
                    }
                });
            }

            function renderThreadsList(threads) {
                const list = document.getElementById('sunny-threads-list');
                if (!list) return;
                let html = '';
                threads.forEach(function(t) {
                    const isActive = (currentThreadId === t.id);
                    const dateStr = t.updated_at ? new Date(t.updated_at.replace(' ', 'T')).toLocaleDateString('fr-FR', { day:'numeric', month:'short' }) : '';
                    const safeTitle = (t.title || 'Nouvelle discussion').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    html += '<div class="sunny-thread-item' + (isActive ? ' active' : '') + '" data-thread-id="' + t.id + '" onclick="sunnySelectThread(' + t.id + ', \'' + safeTitle + '\')">'
                        + '<span class="sunny-thread-icon">' + (isActive ? '🟡' : '🗨️') + '</span>'
                        + '<div class="sunny-thread-info">'
                        + '<div class="sunny-thread-title">' + (t.title || 'Nouvelle discussion') + '</div>'
                        + (t.last_message ? '<div class="sunny-thread-preview">' + t.last_message + '</div>' : '')
                        + '<div class="sunny-thread-meta">'
                        + '<span class="sunny-thread-date">' + dateStr + '</span>'
                        + (t.message_count > 0 ? '<span class="sunny-thread-badge">' + t.message_count + ' msg</span>' : '')
                        + '</div></div>'
                        + '<div class="sunny-thread-actions" onclick="event.stopPropagation()">'
                        + '<button class="sunny-thread-action-btn" onclick="sunnyMoveThreadPrompt(' + t.id + ')" title="Déplacer dans un dossier" data-emoji="📁"></button>'
                        + '<button class="sunny-thread-action-btn" onclick="sunnyRenameThread(' + t.id + ', \'' + safeTitle + '\')" title="Renommer" data-emoji="✏️"></button>'
                        + '<button class="sunny-thread-action-btn delete" onclick="sunnyDeleteThread(' + t.id + ')" title="Supprimer" data-emoji="🗑️"></button>'
                        + '</div></div>';
                });
                list.innerHTML = html;
            }

            function switchToThread(threadId, title) {
                currentThreadId = threadId;
                updateThreadIndicator(title);
                loadChatHistory(currentPoolId, threadId);
                const suggestBar = document.getElementById('sunny-suggestions');
                if (suggestBar) suggestBar.style.display = '';
            }

            window.sunnySelectThread = function(threadId, title) {
                switchToThread(threadId, title);
                sunnyCloseDrawer();
                document.querySelectorAll('.sunny-thread-item').forEach(function(el) {
                    el.classList.toggle('active', parseInt(el.dataset.threadId) === threadId);
                });
            };

            window.sunnyCreateThread = function() {
                $.ajax({
                    url: THREADS_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ pool_id: currentPoolId }),
                    success: function(data) {
                        if (data.success && data.data) {
                            switchToThread(data.data.id, data.data.title);
                            loadThreads();
                            sunnyCloseDrawer();
                            appendBubble('Nouvelle discussion créée ! Posez votre question 🏊', 'system');
                        } else {
                            alert(data.message || 'Erreur lors de la création');
                        }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyRenameThread = function(threadId, currentTitle) {
                const newTitle = prompt('Renommer la discussion :', currentTitle || '');
                if (!newTitle || newTitle.trim() === '' || newTitle === currentTitle) return;

                $.ajax({
                    url: THREADS_URL + '/' + threadId,
                    method: 'PUT',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ title: newTitle.trim() }),
                    success: function(data) {
                        if (data.success) {
                            loadThreads();
                            if (currentThreadId === threadId) updateThreadIndicator(newTitle.trim());
                        } else { alert(data.message || 'Erreur'); }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyDeleteThread = function(threadId) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer cette discussion et tous ses messages ?')) return;

                $.ajax({
                    url: THREADS_URL + '/' + threadId,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success) {
                            if (currentThreadId === threadId) currentThreadId = null;
                            loadThreads(true);
                            appendBubble('🗑️ Discussion supprimée', 'system');
                        } else { alert(data.message || 'Erreur'); }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            // ── FOLDERS MANAGEMENT ────────────────────────────────────
            let foldersCache = [];
            let openFolderIds = new Set();

            function loadFolders() {
                const wrap = document.getElementById('sunny-folders-list');
                if (!wrap) return;
                $.ajax({
                    url: `${FOLDERS_URL}?pool_id=${currentPoolId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        foldersCache = (data && data.success && data.data) ? data.data : [];
                        renderFoldersList(foldersCache);
                    },
                    error: function() { wrap.innerHTML = ''; }
                });
            }

            function renderFoldersList(folders) {
                const wrap = document.getElementById('sunny-folders-list');
                if (!wrap) return;
                if (!folders.length) { wrap.innerHTML = ''; return; }
                let html = '';
                folders.forEach(function(f) {
                    const isOpen = openFolderIds.has(f.id);
                    const safeName = (f.name || 'Dossier').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                    html += '<div class="sunny-folder-item' + (isOpen ? ' open' : '') + '" data-folder-id="' + f.id + '">'
                        + '<div class="sunny-folder-head" onclick="sunnyToggleFolder(' + f.id + ')">'
                        +   '<span class="sunny-folder-caret">▶</span>'
                        +   '<span class="sunny-folder-icon">📁</span>'
                        +   '<span class="sunny-folder-name">' + (f.name || 'Dossier') + '</span>'
                        +   '<span class="sunny-folder-count">' + (f.thread_count || 0) + '</span>'
                        +   '<div class="sunny-folder-actions" onclick="event.stopPropagation()">'
                        +     '<button class="sunny-thread-action-btn" onclick="sunnyRenameFolder(' + f.id + ', \'' + safeName + '\')" title="Renommer" data-emoji="✏️"></button>'
                        +     '<button class="sunny-thread-action-btn delete" onclick="sunnyDeleteFolder(' + f.id + ')" title="Supprimer" data-emoji="🗑️"></button>'
                        +   '</div>'
                        + '</div>'
                        + '<div class="sunny-folder-body" id="folder-body-' + f.id + '">'
                        +   (isOpen ? '<div class="sunny-folder-empty">Chargement...</div>' : '')
                        + '</div>'
                        + '</div>';
                });
                wrap.innerHTML = html;
                openFolderIds.forEach(function(id) { loadFolderThreads(id); });
            }

            window.sunnyToggleFolder = function(folderId) {
                const item = document.querySelector('.sunny-folder-item[data-folder-id="' + folderId + '"]');
                if (!item) return;
                if (openFolderIds.has(folderId)) {
                    openFolderIds.delete(folderId);
                    item.classList.remove('open');
                } else {
                    openFolderIds.add(folderId);
                    item.classList.add('open');
                    loadFolderThreads(folderId);
                }
            };

            function loadFolderThreads(folderId) {
                const body = document.getElementById('folder-body-' + folderId);
                if (!body) return;
                body.innerHTML = '<div class="sunny-folder-empty">Chargement...</div>';
                $.ajax({
                    url: `${FOLDER_THREADS_URL}?folder_id=${folderId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        const threads = (data && data.success && data.data) ? data.data : [];
                        renderFolderThreads(folderId, threads);
                    },
                    error: function() { renderFolderThreads(folderId, []); }
                });
            }

            function renderFolderThreads(folderId, threads) {
                const body = document.getElementById('folder-body-' + folderId);
                if (!body) return;
                let html = '';
                if (!threads.length) {
                    html += '<div class="sunny-folder-empty">Aucune discussion dans ce dossier.</div>';
                } else {
                    threads.forEach(function(t) {
                        const isActive = (currentThreadId === parseInt(t.id));
                        const safeTitle = (t.title || 'Nouvelle discussion').replace(/'/g, "\\'").replace(/"/g, '&quot;');
                        html += '<div class="sunny-thread-item' + (isActive ? ' active' : '') + '" data-thread-id="' + t.id + '" onclick="sunnySelectThread(' + t.id + ', \'' + safeTitle + '\')">'
                            + '<span class="sunny-thread-icon">' + (isActive ? '🟡' : '🗨️') + '</span>'
                            + '<div class="sunny-thread-info"><div class="sunny-thread-title">' + (t.title || 'Nouvelle discussion') + '</div></div>'
                            + '<div class="sunny-thread-actions" onclick="event.stopPropagation()">'
                            +   '<button class="sunny-thread-action-btn" onclick="sunnyMoveThreadToFolder(' + t.id + ', 0)" title="Retirer du dossier" data-emoji="↩️"></button>'
                            +   '<button class="sunny-thread-action-btn delete" onclick="sunnyDeleteThread(' + t.id + ')" title="Supprimer" data-emoji="🗑️"></button>'
                            + '</div></div>';
                    });
                }
                html += '<button class="sunny-folder-new-thread" onclick="sunnyCreateThreadInFolder(' + folderId + ')">➕ Nouvelle discussion ici</button>';
                body.innerHTML = html;
            }

            window.sunnyCreateFolder = function() {
                const name = prompt('Nom du nouveau dossier :', 'Nouveau dossier');
                if (!name || !name.trim()) return;
                $.ajax({
                    url: FOLDER_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ pool_id: currentPoolId, name: name.trim() }),
                    success: function(data) {
                        if (data && data.success) {
                            loadFolders();
                            appendBubble('📁 Dossier créé : ' + name.trim(), 'system');
                        } else { alert((data && data.message) || 'Erreur'); }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyRenameFolder = function(folderId, currentName) {
                const newName = prompt('Renommer le dossier :', currentName || '');
                if (!newName || !newName.trim() || newName === currentName) return;
                $.ajax({
                    url: FOLDER_URL + '/' + folderId + '/rename',
                    method: 'PUT',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ name: newName.trim() }),
                    success: function(data) {
                        if (data && data.success) loadFolders();
                        else alert((data && data.message) || 'Erreur');
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyDeleteFolder = function(folderId) {
                if (!confirm('Supprimer ce dossier et toutes ses discussions ?')) return;
                $.ajax({
                    url: FOLDER_URL + '/' + folderId,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data && data.success) {
                            openFolderIds.delete(folderId);
                            loadThreads(false);
                            appendBubble('🗑️ Dossier supprimé', 'system');
                        } else { alert((data && data.message) || 'Erreur'); }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyMoveThreadPrompt = function(threadId) {
                if (!foldersCache.length) {
                    if (confirm('Aucun dossier existant. Créer un nouveau dossier ?')) sunnyCreateFolder();
                    return;
                }
                let msg = 'Déplacer dans quel dossier ?\n\n0. (Aucun dossier)\n';
                foldersCache.forEach(function(f, i) { msg += (i + 1) + '. ' + f.name + '\n'; });
                const choice = prompt(msg, '1');
                if (choice === null) return;
                const idx = parseInt(choice);
                if (isNaN(idx) || idx < 0 || idx > foldersCache.length) return;
                const folderId = (idx === 0) ? 0 : foldersCache[idx - 1].id;
                sunnyMoveThreadToFolder(threadId, folderId);
            };

            window.sunnyMoveThreadToFolder = function(threadId, folderId) {
                $.ajax({
                    url: FOLDER_URL + '/' + (folderId || 0) + '/thread/' + threadId,
                    method: 'PUT',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data && data.success) loadThreads(false);
                        else alert((data && data.message) || 'Erreur');
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyCreateThreadInFolder = function(folderId) {
                $.ajax({
                    url: THREADS_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ pool_id: currentPoolId }),
                    success: function(data) {
                        if (data && data.success && data.data) {
                            const newId = data.data.id;
                            $.ajax({
                                url: FOLDER_URL + '/' + folderId + '/thread/' + newId,
                                method: 'PUT',
                                headers: { 'X-WP-Nonce': NONCE },
                                complete: function() {
                                    switchToThread(newId, data.data.title);
                                    openFolderIds.add(folderId);
                                    loadThreads();
                                    sunnyCloseDrawer();
                                    appendBubble('Nouvelle discussion créée dans le dossier 📁', 'system');
                                }
                            });
                        } else { alert((data && data.message) || 'Erreur'); }
                    },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            // Pool selector change
            $(document).on('change', '#sunny-pool-selector', function() {
                const newPoolId = parseInt($(this).val());
                if (newPoolId === currentPoolId) return;
                currentPoolId = newPoolId;
                currentThreadId = null;
                loadThreads(true);
                loadAnalyseHistory();
            });

            // Initial load : charger les threads puis sélectionner le plus récent
            setTimeout(function() { loadThreads(true); }, 100);

            // ── PUBLIC FUNCTIONS ─────────────────────────────────────
            // ── CLEAR ANALYSE & OPTIONS FIELDS ────────────────────────
            function clearAnalyseFields() {
                // Vider les champs d'analyse (Mesures d'eau)
                ['ph','chlore','tac','stabilisant','temperature'].forEach(function(f) {
                    const el = document.getElementById('ana-' + f);
                    if (el) el.value = '';
                });
                
                // Reset les checkboxes "Données à inclure" à leurs valeurs par défaut
                const defaultChecked = {
                    'opt-meteo': true,
                    'opt-historique': true,
                    'opt-produits': false,
                    'opt-alertes': true,
                    'opt-planning': false,
                    'opt-coordonnees': true
                };
                Object.keys(defaultChecked).forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el) el.checked = defaultChecked[id];
                });
            }

            window.sunnySend = function() {
                if (isLoading) return;
                const input   = document.getElementById('sunny-input');
                const message = input.value.trim();
                if (!message && !imageBase64) return;

                if (message) appendBubble(message, 'user');
                if (imageBase64) appendBubble('📸 Photo envoyée', 'user');
                input.value = '';
                input.style.height = 'auto';

                // Hide suggestions after first real use
                const suggestBar = document.getElementById('sunny-suggestions');
                if (suggestBar) suggestBar.style.display = 'none';

                const analyse = buildAnalyse();
                
                // Vider les champs d'analyse et reset les options après envoi
                clearAnalyseFields();
                
                sendToSunny(message, imageBase64, analyse);
            };

            window.sunnyQuickSend = function(msg) {
                document.getElementById('sunny-input').value = msg;
                sunnySend();
            };

            window.sunnyLoadImage = function(input) {
                const file = input.files[0];
                if (!file) return;
                if (file.size > 4 * 1024 * 1024) {
                    alert('Image trop lourde (max 4 Mo). Réduisez la taille avant envoi.');
                    input.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = function(e) {
                    imageBase64      = e.target.result;
                    currentImageType = 'general';

                    document.getElementById('img-preview-thumb').src = imageBase64;
                    document.getElementById('img-preview-label').textContent = '📷 Photo';
                    document.getElementById('img-preview-sub').textContent   = 'Image jointe';
                    document.getElementById('sunny-image-preview-bar').classList.add('active');
                };
                reader.readAsDataURL(file);
            };

            window.sunnyRemoveImage = function() {
                imageBase64      = null;
                currentImageType = 'general';
                document.getElementById('sunny-image-preview-bar').classList.remove('active');
                document.getElementById('img-preview-thumb').src = '';
                const el = document.getElementById('sunny-image-file');
                if (el) el.value = '';
            };

            // ── DATA OPTIONS ─────────────────────────────────────────
            function getDataOptions() {
                const get = id => { const el = document.getElementById(id); return el ? el.checked : false; };
                return {
                    meteo:       get('opt-meteo'),
                    historique:  get('opt-historique'),
                    produits:    get('opt-produits'),
                    alertes:     get('opt-alertes'),
                    planning:    get('opt-planning'),
                    coordonnees: get('opt-coordonnees')
                };
            }

            // ── PRODUCTS ────────────────────────────────────────────
            const productCategories = {
                'chlore_choc':'Chlore choc','chlore_lent':'Chlore lent',
                'ph_plus':'pH +','ph_moins':'pH -','anti_algues':'Anti-algues',
                'clarifiant':'Clarifiant','floculant':'Floculant',
                'sequestrant_calcaire':'Séq. calcaire','sequestrant_metaux':'Séq. métaux'
            };

            function fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    if (!file) { resolve(''); return; }
                    const r = new FileReader();
                    r.onload = () => resolve(r.result);
                    r.onerror = reject;
                    r.readAsDataURL(file);
                });
            }

            function loadProducts() {
                const list = document.getElementById('sunny-products-list');
                if (!list) return;
                list.innerHTML = '<div class="sunny-products-empty">Chargement...</div>';
                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success && data.data && data.data.length > 0) {
                            renderProductsList(data.data);
                        } else {
                            list.innerHTML = '<div class="sunny-products-empty">Aucun produit enregistré. Ajoutez-en un ci-dessous.</div>';
                        }
                    },
                    error: function() {
                        list.innerHTML = '<div class="sunny-products-empty">Erreur de chargement.</div>';
                    }
                });
            }

            function renderProductsList(products) {
                const list = document.getElementById('sunny-products-list');
                if (!list) return;
                let html = '';
                products.forEach(function(p) {
                    const cat = productCategories[p.categorie] || p.categorie || '-';
                    html += `<div class="sunny-product-item" data-product-id="${p.id}">
                        <div class="product-info">
                            <span class="product-cat">${cat}</span>
                            <span class="product-brand">${p.marque || '-'}</span>
                            <span class="product-name">${p.nom_produit || '-'}</span>
                        </div>
                        <div class="product-controls">
                            <input type="number" class="product-qty" data-id="${p.id}" value="${p.quantite||0}" step="0.1" min="0">
                            <select class="product-unit" data-id="${p.id}">
                                ${['L','kg','g','ml','unités'].map(u => `<option value="${u}"${p.unite===u?' selected':''}>${u}</option>`).join('')}
                            </select>
                            <button class="save-btn" onclick="sunnyUpdateProduct('${p.id}')" title="Sauvegarder">💾</button>
                            ${p.photo_face   ? `<a href="${p.photo_face}"   target="_blank" class="photo-link" title="Photo face">📷</a>` : ''}
                            ${p.photo_notice ? `<a href="${p.photo_notice}" target="_blank" class="photo-link" title="Notice">📄</a>` : ''}
                            <button class="del-btn" onclick="sunnyDeleteProduct('${p.id}')" title="Supprimer">🗑️</button>
                        </div>
                    </div>`;
                });
                list.innerHTML = html;
            }

            window.sunnyAddProduct = async function() {
                const cat      = document.getElementById('product-categorie').value;
                const marque   = document.getElementById('product-marque').value.trim();
                const nom      = document.getElementById('product-nom').value.trim();
                if (!cat)   { alert('Veuillez sélectionner une catégorie'); return; }
                if (!marque){ alert('Veuillez saisir la marque'); return; }
                if (!nom)   { alert('Veuillez saisir le nom du produit'); return; }

                const quantite    = parseFloat(document.getElementById('product-quantity').value) || 0;
                const unite       = document.getElementById('product-unit').value;
                const commentaire = document.getElementById('product-commentaire').value.trim();
                let photoFaceB64='', photoNoticeB64='';
                try {
                    const fFace   = document.getElementById('product-photo-face').files[0];
                    const fNotice = document.getElementById('product-photo-notice').files[0];
                    if (fFace)   photoFaceB64   = await fileToBase64(fFace);
                    if (fNotice) photoNoticeB64 = await fileToBase64(fNotice);
                } catch(e) { console.error('Image conversion:', e); }

                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ categorie:cat, marque, nom_produit:nom, quantite, unite, commentaire, photo_face_base64:photoFaceB64, photo_notice_base64:photoNoticeB64 }),
                    success: function(data) {
                        if (data.success) {
                            ['product-categorie','product-marque','product-nom','product-quantity','product-commentaire','product-photo-face','product-photo-notice'].forEach(id => {
                                const el = document.getElementById(id);
                                if (el) el.value = '';
                            });
                            document.getElementById('product-unit').value = 'L';
                            loadProducts();
                            appendBubble('✅ Produit ajouté : ' + marque + ' ' + nom, 'system');
                        } else { alert(data.message || 'Erreur lors de l\'ajout'); }
                    },
                    error: function() { alert('Erreur de connexion lors de l\'ajout'); }
                });
            };

            window.sunnyUpdateProduct = function(id) {
                const qty  = document.querySelector('.product-qty[data-id="'+id+'"]');
                const unit = document.querySelector('.product-unit[data-id="'+id+'"]');
                if (!qty || !unit) return;
                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products/' + id,
                    method: 'PUT',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({ quantite: parseFloat(qty.value)||0, unite: unit.value }),
                    success: function(data) { if (data.success) appendBubble('✅ Produit mis à jour', 'system'); else alert(data.message || 'Erreur'); },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            window.sunnyDeleteProduct = function(id) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) return;
                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products/' + id,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) { if (data.success) { loadProducts(); appendBubble('🗑️ Produit supprimé', 'system'); } else alert(data.message || 'Erreur'); },
                    error: function() { alert('Erreur de connexion'); }
                });
            };

            // ── CORE SEND ────────────────────────────────────────────
            function buildAnalyse() {
                const result = {};
                let has = false;
                ['ph','chlore','tac','stabilisant','temperature'].forEach(function(f) {
                    const el = document.getElementById('ana-' + f);
                    if (el && el.value !== '') { result[f] = parseFloat(el.value); has = true; }
                });
                return has ? result : {};
            }

            function sendToSunny(message, imgB64, analyse) {
                // SÉCURITÉ : Vérifier qu'une piscine est sélectionnée
                if (!currentPoolId || currentPoolId === 0) {
                    appendBubble('⚠️ Erreur : Veuillez sélectionner une piscine valide.', 'system');
                    return;
                }

                isLoading = true;
                document.getElementById('sunny-send').disabled = true;

                const typingEl = appendTyping();
                const conversationId = 'conv-<?php echo get_current_user_id(); ?>-' + currentPoolId + '-' + Date.now();

                let imagePure = null;
                if (imgB64) {
                    const m = imgB64.match(/^data:image\/[^;]+;base64,(.+)$/);
                    imagePure = m ? m[1] : imgB64;
                }

                const payload = {
                    message,
                    pool_id:         currentPoolId,
                    thread_id:       currentThreadId || 0,
                    image_base64:    imagePure,
                    image_type:      imgB64 ? (currentImageType || 'general') : 'general',
                    analyse:         Object.keys(analyse).length > 0 ? analyse : null,
                    data_options:    getDataOptions(),
                    conversation_id: conversationId,
                };

                $.ajax({
                    url: API_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify(payload),
                    success: function(data) {
                        if (data.success) {
                            if (data.response && data.response !== 'pending') {
                                // Update thread_id from server response (auto-création)
                                if (data.thread_id && (!currentThreadId || currentThreadId !== data.thread_id)) {
                                    currentThreadId = data.thread_id;
                                    loadThreads();
                                }
                                removeTyping(typingEl);
                                appendBubble(data.response, 'sunny');
                                if (data.alertes && data.alertes.length) appendAlertes(data.alertes);
                                if (data.score_eau !== null && data.score_eau !== undefined) appendScoreEau(data.score_eau);
                                if (data.analyse_extraite) prefillAnalyse(data.analyse_extraite);
                                sunnyRemoveImage();
                                isLoading = false;
                                document.getElementById('sunny-send').disabled = false;
                            } else {
                                // Update thread_id même en mode pending (auto-création)
                                if (data.thread_id && (!currentThreadId || currentThreadId !== data.thread_id)) {
                                    currentThreadId = data.thread_id;
                                    updateThreadIndicator(null); // Sera mis à jour automatiquement après auto-titre
                                    loadThreads();
                                }
                                pollForResponse(data.conversation_id || conversationId, typingEl, 0);
                            }
                        } else {
                            removeTyping(typingEl);
                            appendBubble('⚠️ ' + (data.message || 'Désolé, une erreur est survenue. Réessayez.'), 'system');
                            isLoading = false;
                            document.getElementById('sunny-send').disabled = false;
                        }
                    },
                    error: function(xhr) {
                        removeTyping(typingEl);
                        appendBubble('Erreur de connexion (' + xhr.status + '). Vérifiez votre connexion.', 'system');
                        isLoading = false;
                        document.getElementById('sunny-send').disabled = false;
                    }
                });
            }

            // ══════════════════════════════════════════════════════
            // MODAL ANALYSE — affiche "en cours" puis le résultat
            // ══════════════════════════════════════════════════════

            let currentAnalyseModal = null; // {backdrop, analyseId, analyseData}

            function openAnalyseModal(state, analyseData) {
                // state: 'pending' | 'result'
                const root = document.getElementById('sunny-analyse-modal-root');
                if (!root) return;

                // Fermer modal existant sans animation
                if (currentAnalyseModal && currentAnalyseModal.backdrop) {
                    currentAnalyseModal.backdrop.remove();
                }

                const backdrop = document.createElement('div');
                backdrop.className = 'sunny-modal-backdrop';
                backdrop.id = 'sunny-analyse-modal-backdrop';

                if (state === 'pending') {
                    backdrop.innerHTML = `
                        <div class="sunny-modal">
                            <div class="sunny-modal-header">
                                <div class="sunny-modal-title">📊 Analyse en cours</div>
                                <button class="sunny-modal-close" onclick="closeAnalyseModal()">✕</button>
                            </div>
                            <div class="sunny-modal-body">
                                <div class="sunny-modal-pending">
                                    <div class="sunny-modal-pending-spinner">
                                        <span></span><span></span><span></span>
                                    </div>
                                    <div class="sunny-modal-pending-text">
                                        <strong>Sunny analyse votre eau 🌞</strong>
                                        L'analyse est en cours de traitement.<br>
                                        Cela prend généralement 10 à 30 secondes.
                                    </div>
                                </div>
                            </div>
                        </div>`;
                } else if (state === 'result' && analyseData) {
                    const diagHtml  = buildDiagHtml(analyseData.response_n8n || '');
                    const alertHtml = buildAlertesHtml(analyseData.alertes || []);
                    const dateStr   = analyseData.created_at
                        ? new Date(analyseData.created_at.replace(' ','T')).toLocaleString('fr-FR')
                        : '';

                    backdrop.innerHTML = `
                        <div class="sunny-modal">
                            <div class="sunny-modal-header">
                                <div class="sunny-modal-title">📊 Résultat de l'analyse</div>
                                <button class="sunny-modal-close" onclick="closeAnalyseModal()">✕</button>
                            </div>
                            <div class="sunny-modal-body">
                                ${dateStr ? '<p style="color:var(--text-muted);font-size:0.76em;margin:0 0 12px;">' + dateStr + '</p>' : ''}
                                <p style="color:var(--gold);font-size:0.8em;font-weight:700;margin:0 0 10px;text-transform:uppercase;letter-spacing:0.5px;">🔬 Diagnostic rapide</p>
                                ${diagHtml || '<p style="color:var(--text-muted);font-size:0.85em;">Aucun diagnostic disponible.</p>'}
                                ${alertHtml ? '<p style="color:var(--gold);font-size:0.8em;font-weight:700;margin:16px 0 8px;text-transform:uppercase;letter-spacing:0.5px;">⚠️ Alertes</p>' + alertHtml : ''}
                            </div>
                            <div class="sunny-modal-footer">
                                <button class="btn-close-modal" onclick="closeAnalyseModal()">Fermer</button>
                                <button class="btn-discuss" onclick="discuterAnalyse(${JSON.stringify(analyseData).replace(/"/g, '&quot;')})">
                                    💬 Discuter avec Sunny
                                </button>
                            </div>
                        </div>`;
                }

                root.appendChild(backdrop);
                currentAnalyseModal = { backdrop, analyseId: analyseData ? analyseData.analyse_id : null, analyseData };

                // Fermer en cliquant hors du modal
                backdrop.addEventListener('click', function(e) {
                    if (e.target === backdrop) closeAnalyseModal();
                });
            }

            window.closeAnalyseModal = function() {
                const backdrop = document.getElementById('sunny-analyse-modal-backdrop');
                if (!backdrop) return;
                backdrop.classList.add('closing');
                setTimeout(function() { backdrop.remove(); }, 180);
                currentAnalyseModal = null;
            };

            // ── Construction du diagnostic HTML ─────────────────
            function buildDiagHtml(responseText) {
                if (!responseText) return '';
                const lines = responseText.split('\n').map(l => l.trim()).filter(Boolean);
                let html = '';
                lines.forEach(function(line) {
                    // Lignes qui contiennent une flèche → (lignes de diagnostic)
                    if (!line.match(/→|->|:/) && !line.match(/^[-•*]/)) return;

                    // Détecter statut
                    let cls = '';
                    if (line.includes('🔴') || line.toLowerCase().includes('urgent')) cls = 'urgent';
                    else if (line.includes('⚠️') || line.toLowerCase().includes('corriger')) cls = 'warning';
                    else if (line.includes('✅') || line.toLowerCase().includes('ok')) cls = 'ok';

                    // Extraire emoji de statut
                    let emoji = '•';
                    if (line.includes('🔴')) emoji = '🔴';
                    else if (line.includes('⚠️')) emoji = '⚠️';
                    else if (line.includes('✅')) emoji = '✅';

                    // Nettoyer la ligne
                    const clean = line.replace(/^[-•*]\s*/, '').replace(/^Diagnostic rapide\s*:?\s*/i, '').trim();
                    if (!clean) return;

                    html += `<div class="diag-line ${cls}"><span class="diag-emoji">${emoji}</span><span>${clean}</span></div>`;
                });
                return html || `<div style="color:var(--text-muted);font-size:0.85em;white-space:pre-wrap;">${responseText}</div>`;
            }

            // ── Construction des alertes HTML ────────────────────
            function buildAlertesHtml(alertes) {
                if (!alertes || !alertes.length) return '';
                return '<div class="modal-alertes">'
                    + alertes.map(function(a) {
                        const urg = a.urgence || 'faible';
                        const icon = urg === 'haute' ? '🔴' : (urg === 'moyenne' ? '⚠️' : '⚡');
                        return `<div class="modal-alerte ${urg}"><span class="modal-alerte-icon">${icon}</span><span>${a.msg}</span></div>`;
                    }).join('')
                    + '</div>';
            }

            // ── Polling spécifique analyse ───────────────────────
            function pollAnalyseResult(analyseId, attempts) {
                const maxAttempts = 60;
                const interval    = 3000;

                if (attempts >= maxAttempts) {
                    openAnalyseModal('result', {
                        response_n8n: 'L\'analyse a pris trop de temps. Vérifiez l\'historique dans quelques instants.',
                        alertes: [],
                        created_at: null,
                        analyse_id: analyseId
                    });
                    loadAnalyseHistory();
                    return;
                }

                $.ajax({
                    url: ANALYSE_HISTORY_URL + '?pool_id=' + currentPoolId + '&limit=5',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (!data.success || !data.data) {
                            setTimeout(function() { pollAnalyseResult(analyseId, attempts + 1); }, interval);
                            return;
                        }

                        // Chercher notre analyse dans les résultats
                        const found = data.data.find(function(item) {
                            return item.analyse_id === analyseId && item.status === 'completed' && item.response_n8n;
                        });

                        if (found) {
                            loadAnalyseHistory();
                            openAnalyseModal('result', found);
                        } else {
                            setTimeout(function() { pollAnalyseResult(analyseId, attempts + 1); }, interval);
                        }
                    },
                    error: function() {
                        setTimeout(function() { pollAnalyseResult(analyseId, attempts + 1); }, interval);
                    }
                });
            }

            // ── Bouton "Discuter avec Sunny" ─────────────────────
            window.discuterAnalyse = function(analyseData) {
                closeAnalyseModal();
                sunnyCloseDrawer();

                // Construire le contexte à injecter dans le thread
                const a = analyseData.analyse || {};
                const measures = [];
                if (a.ph          != null) measures.push('pH ' + a.ph);
                if (a.chlore      != null) measures.push('Chlore ' + a.chlore + ' mg/L');
                if (a.tac         != null) measures.push('TAC ' + a.tac + ' mg/L');
                if (a.stabilisant != null) measures.push('Stabilisant ' + a.stabilisant + ' mg/L');
                if (a.temperature != null) measures.push('Temp ' + a.temperature + '°C');

                const contextMsg = '📊 Discussion basée sur mon analyse du '
                    + (analyseData.created_at ? new Date(analyseData.created_at.replace(' ','T')).toLocaleDateString('fr-FR') : 'aujourd\'hui')
                    + '\nMesures : ' + (measures.join(' | ') || 'voir historique')
                    + (analyseData.response_n8n ? '\n\nDiagnostic :\n' + analyseData.response_n8n.substring(0, 400) : '');

                // Créer un thread, puis y envoyer le contexte automatiquement
                $.ajax({
                    url: THREADS_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({
                        pool_id: currentPoolId,
                        title: '📊 Analyse ' + (analyseData.created_at ? new Date(analyseData.created_at.replace(' ','T')).toLocaleDateString('fr-FR') : '')
                    }),
                    success: function(data) {
                        if (data.success && data.data) {
                            switchToThread(data.data.id, data.data.title);
                            loadThreads();

                            // Pré-remplir la zone de texte avec le contexte
                            const input = document.getElementById('sunny-input');
                            if (input) {
                                input.value = 'Sur la base de cette analyse :\n' + (analyseData.response_n8n ? analyseData.response_n8n.substring(0, 300) : contextMsg) + '\n\nQue dois-je faire en priorité ?';
                                input.style.height = 'auto';
                                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
                                input.focus();
                            }

                            appendBubble('📊 Discussion ouverte à partir de votre analyse. La zone de texte a été pré-remplie avec le diagnostic — modifiez-la ou envoyez directement.', 'system');
                        } else {
                            sunnyToast('Erreur lors de la création de la discussion.', 'error');
                        }
                    },
                    error: function() { sunnyToast('Erreur de connexion.', 'error'); }
                });
            };

            // ── Discuter depuis l'historique ─────────────────────
            window.discuterDepuisHistorique = function(analyseDataJson) {
                var analyseData;
                try { analyseData = JSON.parse(decodeURIComponent(analyseDataJson)); } catch(e) { return; }
                discuterAnalyse(analyseData);
            };

            // ── Voir diagnostic depuis l'historique ──────────────
            window.toggleDiagHistorique = function(id) {
                const el = document.getElementById('diag-hist-' + id);
                const btn = document.getElementById('diag-btn-' + id);
                if (!el) return;
                const expanded = el.classList.toggle('expanded');
                if (btn) btn.textContent = expanded ? '▲ Masquer' : '▼ Diagnostic';
            };

            // ══════════════════════════════════════════════════════
            // HISTORIQUE ENRICHI
            // ══════════════════════════════════════════════════════
            function renderAnalyseHistory(items) {
                const container = document.getElementById('sunny-analyse-history-list');
                if (!container) return;

                if (!items || !items.length) {
                    container.innerHTML = '<div class="analyse-history-item" style="color:var(--text-muted);font-style:italic;">Aucune analyse enregistrée.</div>';
                    return;
                }

                let html = '';
                items.forEach(function(item, idx) {
                    const date = item.created_at
                        ? new Date(item.created_at.replace(' ', 'T')).toLocaleString('fr-FR', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' })
                        : '-';
                    const status = item.status || 'pending';
                    const a = item.analyse || {};

                    const pills = [];
                    if (a.ph          != null) pills.push('<span class="analyse-pill">pH ' + a.ph + '</span>');
                    if (a.chlore      != null) pills.push('<span class="analyse-pill">Cl ' + a.chlore + '</span>');
                    if (a.tac         != null) pills.push('<span class="analyse-pill">TAC ' + a.tac + '</span>');
                    if (a.stabilisant != null) pills.push('<span class="analyse-pill">Stab ' + a.stabilisant + '</span>');
                    if (a.temperature != null) pills.push('<span class="analyse-pill">' + a.temperature + '°C</span>');

                    const hasDiag = !!(item.response_n8n);
                    const safeData = encodeURIComponent(JSON.stringify(item));
                    const statusIcon = status === 'completed' ? '✅' : (status === 'error' ? '❌' : '⏳');

                    html += '<div class="analyse-history-item">'
                        + '<div class="analyse-history-item-header">'
                        + '<span style="color:var(--text-muted);font-size:0.74em;">' + date + '</span>'
                        + '<span class="analyse-history-status ' + status + '">' + statusIcon + ' ' + status + '</span>'
                        + '</div>'
                        + '<div class="analyse-history-values">' + (pills.join('') || '<span style="color:var(--text-muted);font-size:0.78em;">—</span>') + '</div>'
                        + '<div class="analyse-history-actions">';

                    if (hasDiag) {
                        html += '<button class="btn-history-toggle" id="diag-btn-' + idx + '" onclick="toggleDiagHistorique(' + idx + ')">▼ Diagnostic</button>';
                    }
                    if (status === 'completed') {
                        html += '<button class="btn-history-discuss" onclick="discuterDepuisHistorique(\'' + safeData.replace(/'/g, "\\'") + '\')">💬 Discuter</button>';
                    }

                    html += '</div>';

                    if (hasDiag) {
                        html += '<div class="analyse-history-diag" id="diag-hist-' + idx + '">' + escapeHtml(item.response_n8n) + '</div>';
                    }

                    html += '</div>';
                });
                container.innerHTML = html;
            }

            function escapeHtml(str) {
                return String(str)
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
            }

            function loadAnalyseHistory() {
                const container = document.getElementById('sunny-analyse-history-list');
                if (!container) return;
                if (!currentPoolId) {
                    container.innerHTML = '<div class="analyse-history-item" style="color:var(--text-muted);">Aucune piscine sélectionnée.</div>';
                    return;
                }
                container.innerHTML = '<div class="analyse-history-item" style="color:var(--text-muted);font-style:italic;">Chargement...</div>';
                $.ajax({
                    url: ANALYSE_HISTORY_URL + '?pool_id=' + currentPoolId + '&limit=20',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success) renderAnalyseHistory(data.data || []);
                        else container.innerHTML = '<div class="analyse-history-item" style="color:var(--text-muted);">Impossible de charger l\'historique.</div>';
                    },
                    error: function() {
                        container.innerHTML = '<div class="analyse-history-item" style="color:var(--text-muted);">Erreur de chargement.</div>';
                    }
                });
            }

            // ══════════════════════════════════════════════════════
            // SOUMISSION ANALYSE — ouvre modal "en cours" + poll
            // ══════════════════════════════════════════════════════
            window.sunnySubmitWaterAnalyse = function() {
                if (!currentPoolId) {
                    sunnyToast('Sélectionnez une piscine avant de lancer une analyse.', 'warning');
                    return;
                }

                const analyse = buildAnalyse();
                if (!Object.keys(analyse).length) {
                    sunnyToast('Saisissez au moins une mesure (pH, chlore, TAC, stabilisant, température).', 'warning');
                    return;
                }

                const payload = {
                    pool_id: currentPoolId,
                    analyse: analyse,
                    photo_bandelette_base64: currentImageType === 'water' ? imageBase64 : null
                };

                $.ajax({
                    url: ANALYSE_URL,
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify(payload),
                    success: function(data) {
                        if (data.success) {
                            // 1. Afficher le modal "en cours" immédiatement
                            openAnalyseModal('pending', null);

                            // 2. Lancer le polling pour attendre le résultat n8n
                            setTimeout(function() {
                                pollAnalyseResult(data.analyse_id, 0);
                            }, 3000); // attendre 3s avant le 1er poll

                            // 3. Feedback dans le chat
                            appendBubble('📊 Analyse envoyée à Sunny (ID: ' + data.analyse_id + '). Le résultat s\'affichera dans une fenêtre.', 'system');

                            // 4. Vider les champs d'analyse et reset les options
                            clearAnalyseFields();

                            // 5. Rafraîchir l'historique (statut pending visible)
                            loadAnalyseHistory();
                        } else {
                            sunnyToast(data.message || 'Erreur lors de l\'analyse', 'error');
                        }
                    },
                    error: function(xhr) {
                        const msg = (xhr.responseJSON && xhr.responseJSON.message)
                            ? xhr.responseJSON.message
                            : ('Erreur analyse (' + xhr.status + ')');
                        sunnyToast(msg, 'error');
                    }
                });
            };

            function pollForResponse(convId, typingEl, attempts) {
                const maxAttempts = 60;
                const interval    = 2000;
                if (attempts >= maxAttempts) {
                    removeTyping(typingEl);
                    appendBubble('Sunny met plus de temps que prévu... Vérifiez dans quelques secondes.', 'system');
                    isLoading = false;
                    document.getElementById('sunny-send').disabled = false;
                    return;
                }
                $.ajax({
                    url: '<?php echo esc_url(rest_url("sunny-pool/v1/chat/poll")); ?>?conversation_id=' + encodeURIComponent(convId),
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.found && data.response) {
                            removeTyping(typingEl);
                            appendBubble(data.response, 'sunny');
                            if (data.alertes && data.alertes.length) appendAlertes(data.alertes);
                            if (data.score_eau !== null && data.score_eau !== undefined) appendScoreEau(data.score_eau);
                            if (data.analyse_extraite) prefillAnalyse(data.analyse_extraite);
                            sunnyRemoveImage();
                            isLoading = false;
                            document.getElementById('sunny-send').disabled = false;
                        } else {
                            setTimeout(function() { pollForResponse(convId, typingEl, attempts + 1); }, interval);
                        }
                    },
                    error: function() {
                        setTimeout(function() { pollForResponse(convId, typingEl, attempts + 1); }, interval);
                    }
                });
            }

            // ── HELPERS ──────────────────────────────────────────────
            function appendBubble(text, type, isHTML) {
                const msgs = document.getElementById('sunny-messages');
                const div  = document.createElement('div');
                div.className = 'chat-bubble ' + type;

                const structured = (type === 'sunny') ? normalizeSunnyPayload(text) : null;
                if (structured) {
                    renderSunnyPayload(div, structured);
                } else if (isHTML) {
                    div.innerHTML = text;
                } else if (type === 'sunny' && typeof text === 'string') {
                    div.innerHTML = formatResponse(text);
                } else if (text === null || text === undefined) {
                    div.textContent = '';
                } else {
                    div.textContent = String(text);
                }

                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
                return div;
            }

            function appendTyping() {
                const msgs = document.getElementById('sunny-messages');
                const div  = document.createElement('div');
                div.className = 'typing-indicator';
                div.innerHTML = '<span></span><span></span><span></span>';
                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
                return div;
            }

            function removeTyping(el) {
                if (el && el.parentNode) el.parentNode.removeChild(el);
            }

            function appendAlertes(alertes) {
                const msgs = document.getElementById('sunny-messages');
                const div  = document.createElement('div');
                div.className = 'sunny-alertes';
                alertes.forEach(function(a) {
                    const al = document.createElement('div');
                    al.className  = 'sunny-alerte ' + (a.urgence || 'faible');
                    al.textContent = '⚠️ ' + a.msg;
                    div.appendChild(al);
                });
                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
            }

            function formatResponse(text) {
                if (typeof text !== 'string') return '';
                return text
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n/g,'<br>');
            }

            function normalizeSunnyPayload(value) {
                let payload = value;
                if (!payload) return null;

                if (typeof payload === 'string') {
                    const trimmed = payload.trim();
                    if (!trimmed) return null;
                    if ((trimmed[0] === '{' && trimmed[trimmed.length - 1] === '}') || (trimmed[0] === '[' && trimmed[trimmed.length - 1] === ']')) {
                        try { payload = JSON.parse(trimmed); }
                        catch (_) { return null; }
                    } else {
                        return null;
                    }
                }

                if (typeof payload !== 'object' || Array.isArray(payload)) return null;
                const hasKnownFields = payload.message || payload.diagnosis || payload.actions || payload.products || payload.links || payload.warnings || payload.questions;
                return hasKnownFields ? payload : null;
            }

            function safeArray(input) {
                return Array.isArray(input) ? input : [];
            }

            function safePriority(value) {
                const v = String(value || '').toLowerCase();
                return (v === 'high' || v === 'medium' || v === 'low') ? v : 'low';
            }

            function safeSeverityLabel(value) {
                const map = { high: 'Élevée', medium: 'Moyenne', low: 'Faible' };
                const key = safePriority(value);
                return map[key] || 'Faible';
            }

            function safeUrl(url) {
                if (typeof url !== 'string') return null;
                const trimmed = url.trim();
                if (!trimmed) return null;
                if (/^https?:\/\//i.test(trimmed)) return trimmed;
                return null;
            }

            function createSection(title) {
                const section = document.createElement('section');
                section.className = 'sunny-rich-section';
                const h = document.createElement('h4');
                h.className = 'sunny-rich-title';
                h.textContent = title;
                section.appendChild(h);
                return section;
            }

            function createBadge(level, prefix) {
                const badge = document.createElement('span');
                badge.className = 'sunny-rich-badge ' + safePriority(level);
                badge.textContent = (prefix ? (prefix + ': ') : '') + safeSeverityLabel(level);
                return badge;
            }

            function renderSunnyPayload(container, payload) {
                const root = document.createElement('div');
                root.className = 'sunny-rich';

                if (payload.message) {
                    const p = document.createElement('p');
                    p.className = 'sunny-rich-message';
                    p.textContent = String(payload.message);
                    root.appendChild(p);
                }

                if (payload.diagnosis && (payload.diagnosis.summary || payload.diagnosis.severity)) {
                    const section = createSection('Diagnostic');
                    const row = document.createElement('div');
                    row.className = 'sunny-rich-diagnosis';
                    const summary = document.createElement('p');
                    summary.textContent = payload.diagnosis.summary ? String(payload.diagnosis.summary) : 'Diagnostic disponible';
                    row.appendChild(summary);
                    if (payload.diagnosis.severity) row.appendChild(createBadge(payload.diagnosis.severity, 'Niveau'));
                    section.appendChild(row);
                    root.appendChild(section);
                }

                const actions = safeArray(payload.actions);
                if (actions.length) {
                    const section = createSection('Actions recommandées');
                    const list = document.createElement('ol');
                    list.className = 'sunny-rich-list';
                    actions.forEach(function(action) {
                        if (!action) return;
                        const li = document.createElement('li');
                        const title = document.createElement('span');
                        title.className = 'sunny-rich-item-title';
                        title.textContent = String(action.title || 'Action');
                        li.appendChild(title);
                        if (action.priority) li.appendChild(createBadge(action.priority));
                        if (action.description) {
                            const desc = document.createElement('div');
                            desc.className = 'sunny-rich-item-description';
                            desc.textContent = String(action.description);
                            li.appendChild(desc);
                        }
                        list.appendChild(li);
                    });
                    if (list.children.length) {
                        section.appendChild(list);
                        root.appendChild(section);
                    }
                }

                const products = safeArray(payload.products);
                if (products.length) {
                    const section = createSection('Produits conseillés');
                    const list = document.createElement('ul');
                    list.className = 'sunny-rich-list';
                    products.forEach(function(product) {
                        if (!product) return;
                        const li = document.createElement('li');
                        const title = document.createElement('span');
                        title.className = 'sunny-rich-item-title';
                        title.textContent = String(product.name || 'Produit');
                        li.appendChild(title);
                        const parts = [];
                        if (product.reason) parts.push(String(product.reason));
                        if (product.dosage) parts.push('Dosage: ' + String(product.dosage));
                        if (parts.length) {
                            const desc = document.createElement('div');
                            desc.className = 'sunny-rich-item-description';
                            desc.textContent = parts.join(' • ');
                            li.appendChild(desc);
                        }
                        list.appendChild(li);
                    });
                    if (list.children.length) {
                        section.appendChild(list);
                        root.appendChild(section);
                    }
                }

                const links = safeArray(payload.links);
                if (links.length) {
                    const section = createSection('Liens utiles');
                    const list = document.createElement('ul');
                    list.className = 'sunny-rich-link-list';
                    links.forEach(function(link) {
                        if (!link) return;
                        const href = safeUrl(link.url);
                        if (!href) return;
                        const li = document.createElement('li');
                        const a = document.createElement('a');
                        a.className = 'sunny-rich-link';
                        a.href = href;
                        a.target = '_blank';
                        a.rel = 'noopener noreferrer';
                        a.textContent = '🔗 ' + String(link.title || 'Ouvrir le lien');
                        li.appendChild(a);
                        list.appendChild(li);
                    });
                    if (list.children.length) {
                        section.appendChild(list);
                        root.appendChild(section);
                    }
                }

                const warnings = safeArray(payload.warnings);
                if (warnings.length) {
                    const section = createSection('Avertissements');
                    const list = document.createElement('ul');
                    list.className = 'sunny-rich-list';
                    warnings.forEach(function(item) {
                        const li = document.createElement('li');
                        li.textContent = String(item);
                        list.appendChild(li);
                    });
                    section.appendChild(list);
                    root.appendChild(section);
                }

                const questions = safeArray(payload.questions);
                if (questions.length) {
                    const section = createSection('Questions de suivi');
                    const list = document.createElement('ul');
                    list.className = 'sunny-rich-list';
                    questions.forEach(function(item) {
                        const li = document.createElement('li');
                        li.textContent = String(item);
                        list.appendChild(li);
                    });
                    section.appendChild(list);
                    root.appendChild(section);
                }

                container.appendChild(root);
            }

            function prefillAnalyse(analyse) {
                if (!analyse) return;
                const map = { ph:'ana-ph', chlore:'ana-chlore', tac:'ana-tac', stabilisant:'ana-stabilisant', temperature:'ana-temperature' };
                let filled = 0;
                Object.keys(map).forEach(function(k) {
                    if (analyse[k] !== null && analyse[k] !== undefined) {
                        const el = document.getElementById(map[k]);
                        if (el) { el.value = analyse[k]; filled++; }
                    }
                });
                if (filled > 0) {
                    sunnyOpenDrawer('analyse');
                    appendBubble('✅ ' + filled + ' valeur(s) extraite(s) de l\'image et pré-remplies dans le formulaire.', 'system');
                }
            }

            function appendScoreEau(score) {
                if (score === null || score === undefined) return;
                const msgs = document.getElementById('sunny-messages');
                const div  = document.createElement('div');
                div.style.cssText = 'align-self:center;margin:4px 0;font-size:0.86em;color:#d4af37;';
                let emoji, label, color;
                if      (score >= 85) { emoji='🟢'; label='Excellente'; color='#2ecc71'; }
                else if (score >= 65) { emoji='🟡'; label='Correcte';   color='#f39c12'; }
                else if (score >= 40) { emoji='🟠'; label='À corriger'; color='#e67e22'; }
                else                  { emoji='🔴'; label='Critique';   color='#e74c3c'; }
                div.innerHTML = emoji + ' <strong style="color:'+color+'">Qualité eau : '+label+' ('+score+'/100)</strong>';
                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
            }

            // ── TOAST NOTIFICATIONS ──────────────────────────────────
            window.sunnyToast = function(message, type, duration) {
                type = type || 'info';
                duration = duration || 4000;
                
                const container = document.getElementById('sunny-toast-container');
                if (!container) return;
                
                const icons = {
                    success: '✅',
                    error: '❌',
                    warning: '⚠️',
                    info: 'ℹ️'
                };
                
                const toast = document.createElement('div');
                toast.className = 'sunny-toast ' + type;
                toast.innerHTML = `
                    <span class="sunny-toast-icon">${icons[type] || icons.info}</span>
                    <span class="sunny-toast-content">${message}</span>
                    <button class="sunny-toast-close" onclick="this.parentElement.remove()">✕</button>
                `;
                
                container.appendChild(toast);
                
                // Auto remove after duration
                setTimeout(function() {
                    toast.classList.add('hiding');
                    setTimeout(function() {
                        if (toast.parentElement) toast.remove();
                    }, 200);
                }, duration);
            };

            // ── SKELETON LOADING ────────────────────────────────────
            window.sunnyShowSkeleton = function(container, count) {
                count = count || 3;
                let html = '<div class="sunny-loading-dots">';
                for (let i = 0; i < 3; i++) {
                    html += '<span></span>';
                }
                html += '</div>';
                container.innerHTML = html;
            };

            // ── EMPTY STATE ─────────────────────────────────────────
            window.sunnyShowEmptyState = function(container, icon, text) {
                container.innerHTML = `
                    <div class="sunny-empty-state">
                        <div class="sunny-empty-state-icon">${icon || '📭'}</div>
                        <div class="sunny-empty-state-text">${text || 'Aucune donnée disponible'}</div>
                    </div>
                `;
            };

            // ── KEYBOARD SHORTCUTS ─────────────────────────────────
            document.addEventListener('keydown', function(e) {
                // Escape to close drawers
                if (e.key === 'Escape') {
                    if (currentDrawer) sunnyCloseDrawer();
                }
                
                // Ctrl/Cmd + Enter to send
                if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                    const input = document.getElementById('sunny-input');
                    if (input && document.activeElement === input) {
                        sunnySend();
                    }
                }
            });

            // ── TOUCH GESTURES (Swipe to close drawer) ─────────────
            let touchStartY = 0;
            let touchEndY = 0;
            
            document.addEventListener('touchstart', function(e) {
                if (e.target.closest('.sunny-drawer')) {
                    touchStartY = e.changedTouches[0].screenY;
                }
            }, { passive: true });
            
            document.addEventListener('touchend', function(e) {
                if (e.target.closest('.sunny-drawer') && currentDrawer) {
                    touchEndY = e.changedTouches[0].screenY;
                    if (touchStartY - touchEndY < -80) { // Swipe down
                        sunnyCloseDrawer();
                    }
                }
            }, { passive: true });


            // ── SCROLL FAB & ENHANCEMENTS ──────────────────────────
            (function setupScrollFab() {
                const msgs = document.getElementById('sunny-messages');
                const fab  = document.getElementById('sunny-scroll-fab');
                if (!msgs || !fab) return;
                const SCROLL_THRESHOLD = 120;
                function update() {
                    const distFromBottom = msgs.scrollHeight - msgs.scrollTop - msgs.clientHeight;
                    fab.classList.toggle('visible', distFromBottom > SCROLL_THRESHOLD);
                }
                msgs.addEventListener('scroll', update, { passive: true });
                // Watch DOM mutations to refresh visibility on new messages
                const mo = new MutationObserver(update);
                mo.observe(msgs, { childList: true, subtree: false });
                window.sunnyScrollToBottom = function() {
                    msgs.scrollTo({ top: msgs.scrollHeight, behavior: 'smooth' });
                };
                update();
            })();

            // ── HORIZONTAL SWIPE-TO-CLOSE FOR DRAWERS ──────────────
            (function setupDrawerSwipe() {
                document.querySelectorAll('.sunny-drawer').forEach(function(drawer) {
                    let startX = 0, startY = 0, currentX = 0, dragging = false, axis = null;
                    drawer.addEventListener('touchstart', function(e) {
                        if (!drawer.classList.contains('open')) return;
                        const t = e.touches[0];
                        startX = t.clientX; startY = t.clientY; currentX = 0; dragging = true; axis = null;
                        drawer.style.transition = 'none';
                    }, { passive: true });
                    drawer.addEventListener('touchmove', function(e) {
                        if (!dragging) return;
                        const t = e.touches[0];
                        const dx = t.clientX - startX;
                        const dy = t.clientY - startY;
                        if (!axis) axis = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
                        const isMobile = window.matchMedia('(max-width: 720px)').matches;
                        if (isMobile && axis === 'y' && dy > 0) {
                            currentX = dy;
                            drawer.style.transform = 'translateY(' + dy + 'px)';
                        } else if (!isMobile && axis === 'x' && dx > 0) {
                            currentX = dx;
                            drawer.style.transform = 'translateX(' + dx + 'px)';
                        }
                    }, { passive: true });
                    drawer.addEventListener('touchend', function() {
                        if (!dragging) return;
                        dragging = false;
                        drawer.style.transition = '';
                        drawer.style.transform = '';
                        if (currentX > 100) sunnyCloseDrawer();
                    }, { passive: true });
                });
            })();

            // ── ESC TO CLOSE DRAWER ────────────────────────────────
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    if (typeof currentDrawer !== 'undefined' && currentDrawer) sunnyCloseDrawer();
                }
            });

            document.body.classList.add('sunny-chat-active');

            // Initialize
            console.log('[Sunny Chat] UX/UI Enhanced v2.0 initialized');
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
