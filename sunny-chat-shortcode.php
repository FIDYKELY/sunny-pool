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
        return '<p style="color:#d4af37;">Veuillez vous <a href="' . wp_login_url(get_permalink()) . '" style="color:#ffd700;">connecter</a> pour parler à Sunny.</p>';
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

    <!-- ===== STYLES ===== -->
    <style>
    /* ── RESET & VARIABLES ─────────────────────────────────── */
    :root {
        --gold:        #d4af37;
        --gold-light:  #ffd700;
        --gold-dim:    rgba(212,175,55,0.18);
        --gold-border: rgba(212,175,55,0.35);
        --gold-glow:   rgba(212,175,55,0.4);
        --bg-deep:     #111317;
        --bg-card:     #1c1f26;
        --bg-input:    #232730;
        --text-main:   #eef0f5;
        --text-muted:  #8a8f9e;
        --radius-lg:   18px;
        --radius-md:   12px;
        --radius-sm:   8px;
        --radius-xl:   24px;
        --shadow-gold: 0 0 28px rgba(212,175,55,0.18);
        --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
        --shadow-lift: 0 8px 32px rgba(0,0,0,0.5);
        --transition:  0.22s cubic-bezier(.4,0,.2,1);
        --transition-bounce: 0.4s cubic-bezier(0.68,-0.55,0.265,1.55);
        --safe-bottom: env(safe-area-inset-bottom, 0px);
    }

    .sunny-chat-wrapper *,
    .sunny-chat-wrapper *::before,
    .sunny-chat-wrapper *::after {
        box-sizing: border-box;
    }

    /* ── WRAPPER ──────────────────────────────────────────── */
    .sunny-chat-wrapper {
        font-family: 'Segoe UI', system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
        max-width: 900px;
        margin: 20px auto;
        position: relative;
        border-radius: var(--radius-xl);
        overflow: hidden;
        background: linear-gradient(145deg, var(--bg-deep) 0%, #0d0f12 100%);
        box-shadow: 
            0 20px 60px rgba(0,0,0,0.6),
            0 0 0 1px var(--gold-border),
            inset 0 1px 0 rgba(255,255,255,0.05);
        animation: wrapper-enter 0.6s var(--transition) both;
        will-change: transform, opacity;
    }

    @keyframes wrapper-enter {
        from { 
            opacity: 0; 
            transform: translateY(20px) scale(0.98);
            box-shadow: 0 0 0 rgba(0,0,0,0);
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1);
            box-shadow: 0 20px 60px rgba(0,0,0,0.6), 0 0 0 1px var(--gold-border), inset 0 1px 0 rgba(255,255,255,0.05);
        }
    }

    /* Skeleton loading animation */
    @keyframes shimmer {
        0% { background-position: -200% 0; }
        100% { background-position: 200% 0; }
    }

    .sunny-skeleton {
        background: linear-gradient(90deg, var(--bg-card) 25%, rgba(212,175,55,0.1) 50%, var(--bg-card) 75%);
        background-size: 200% 100%;
        animation: shimmer 1.5s infinite;
        border-radius: var(--radius-sm);
    }

    /* ── HEADER ───────────────────────────────────────────── */
    .sunny-chat-header {
        background: linear-gradient(135deg, rgba(25,28,35,0.95) 0%, rgba(34,38,47,0.98) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--gold-border);
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 10;
    }

    .sunny-avatar {
        width: 46px; height: 46px;
        background: linear-gradient(135deg, #c9a43f 0%, var(--gold-light) 50%, #b8942c 100%);
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.4em;
        flex-shrink: 0;
        box-shadow: 
            0 0 0 2px var(--bg-deep),
            0 0 20px rgba(212,175,55,0.4),
            inset 0 2px 4px rgba(255,255,255,0.3);
        animation: avatar-pulse 3s ease-in-out infinite;
        position: relative;
    }

    .sunny-avatar::after {
        content: '';
        position: absolute;
        inset: -3px;
        border-radius: 50%;
        background: conic-gradient(from 0deg, transparent, var(--gold), transparent);
        animation: rotate 3s linear infinite;
        z-index: -1;
        opacity: 0.6;
    }

    @keyframes rotate {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }

    @keyframes avatar-pulse {
        0%, 100% { box-shadow: 0 0 0 2px var(--bg-deep), 0 0 20px rgba(212,175,55,0.4), inset 0 2px 4px rgba(255,255,255,0.3); }
        50% { box-shadow: 0 0 0 2px var(--bg-deep), 0 0 30px rgba(212,175,55,0.6), inset 0 2px 4px rgba(255,255,255,0.3); }
    }

    .sunny-header-info { flex: 1; min-width: 0; }

    .sunny-chat-header h3 {
        color: var(--gold-light);
        margin: 0 0 3px;
        font-size: 1em;
        font-weight: 700;
        letter-spacing: 0.3px;
        text-shadow: 0 1px 2px rgba(0,0,0,0.3);
    }

    .sunny-status-row {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }

    .sunny-status-dot {
        width: 8px; height: 8px;
        background: linear-gradient(135deg, #2ecc71, #27ae60);
        border-radius: 50%;
        flex-shrink: 0;
        box-shadow: 0 0 8px rgba(46,204,113,0.6);
        animation: pulse-dot 2s ease-in-out infinite;
        position: relative;
    }

    .sunny-status-dot::after {
        content: '';
        position: absolute;
        inset: -2px;
        border-radius: 50%;
        background: transparent;
        border: 1px solid rgba(46,204,113,0.3);
        animation: pulse-ring 2s ease-out infinite;
    }

    @keyframes pulse-dot {
        0%, 100% { transform: scale(1); opacity: 1; }
        50% { transform: scale(0.85); opacity: 0.8; }
    }

    @keyframes pulse-ring {
        0% { transform: scale(1); opacity: 1; }
        100% { transform: scale(2.5); opacity: 0; }
    }

    .sunny-status-text {
        color: var(--text-muted);
        font-size: 0.78em;
        font-weight: 500;
    }

    .sunny-pool-selector-wrap {
        display: flex; align-items: center; gap: 6px;
    }

    .sunny-pool-selector-wrap select {
        background: rgba(255,255,255,0.05);
        border: 1px solid var(--gold-border);
        color: var(--gold);
        border-radius: var(--radius-sm);
        padding: 5px 28px 5px 10px;
        font-size: 0.8em;
        cursor: pointer;
        max-width: 180px;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23d4af37' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 8px center;
        transition: all var(--transition);
    }

    .sunny-pool-selector-wrap select:hover,
    .sunny-pool-selector-wrap select:focus {
        background: rgba(255,255,255,0.1);
        border-color: var(--gold-light);
        outline: none;
    }

    /* Header action buttons */
    .sunny-header-actions { display: flex; gap: 8px; }

    .sunny-hdr-btn {
        width: 36px; height: 36px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(145deg, rgba(212,175,55,0.15), rgba(212,175,55,0.05));
        border: 1px solid var(--gold-border);
        border-radius: var(--radius-sm);
        color: var(--gold);
        cursor: pointer;
        font-size: 1em;
        transition: all var(--transition);
        position: relative;
        overflow: hidden;
    }

    .sunny-hdr-btn::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: radial-gradient(circle, rgba(255,215,0,0.3) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.4s, height 0.4s;
    }

    .sunny-hdr-btn:hover,
    .sunny-hdr-btn.active {
        background: linear-gradient(145deg, rgba(212,175,55,0.3), rgba(212,175,55,0.15));
        border-color: var(--gold-light);
        box-shadow: 0 0 15px rgba(212,175,55,0.3);
        transform: translateY(-1px);
    }

    .sunny-hdr-btn:hover::before,
    .sunny-hdr-btn.active::before {
        width: 60px;
        height: 60px;
    }

    .sunny-hdr-btn:active {
        transform: translateY(0);
    }

    /* ── MESSAGES ─────────────────────────────────────────── */
    .sunny-chat-messages {
        background: linear-gradient(180deg, var(--bg-deep) 0%, #0d0f12 100%);
        height: min(60vh, 480px);
        min-height: 300px;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        scroll-behavior: smooth;
        position: relative;
    }

    /* ── DISCUSSIONS BAR ─────────────────────────────────── */
    .sunny-discussions-bar {
        background: linear-gradient(135deg, rgba(25,28,35,0.95), rgba(34,38,47,0.98));
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-bottom: 1px solid var(--gold-border);
        padding: 10px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        position: relative;
        z-index: 9;
    }

    .sunny-new-discussion-btn {
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        color: #000;
        border: none;
        padding: 8px 16px;
        border-radius: 20px;
        cursor: pointer;
        font-weight: 700;
        font-size: 0.8em;
        white-space: nowrap;
        transition: all var(--transition);
        box-shadow: 0 2px 12px rgba(212,175,55,0.3);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .sunny-new-discussion-btn:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 20px rgba(212,175,55,0.5);
    }

    .sunny-discussions-list {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        flex: 1;
        padding-bottom: 4px;
        scrollbar-width: thin;
        scrollbar-color: var(--gold-border) transparent;
    }

    .sunny-discussions-list::-webkit-scrollbar {
        height: 4px;
    }

    .sunny-discussions-list::-webkit-scrollbar-track {
        background: transparent;
    }

    .sunny-discussions-list::-webkit-scrollbar-thumb {
        background: var(--gold-border);
        border-radius: 2px;
    }

    .discussion-chip {
        background: linear-gradient(145deg, var(--bg-card), #252a33);
        border: 1px solid var(--gold-border);
        color: var(--text-muted);
        padding: 6px 14px;
        border-radius: 16px;
        font-size: 0.8em;
        cursor: pointer;
        white-space: nowrap;
        transition: all var(--transition);
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 6px;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .discussion-chip:hover {
        border-color: var(--gold-light);
        color: var(--text-main);
        background: linear-gradient(145deg, rgba(212,175,55,0.15), rgba(212,175,55,0.05));
        transform: translateY(-1px);
    }

    .discussion-chip.active {
        background: linear-gradient(135deg, rgba(212,175,55,0.25), rgba(212,175,55,0.1));
        border-color: var(--gold-light);
        color: var(--gold-light);
        box-shadow: 0 0 15px rgba(212,175,55,0.2);
    }

    .discussion-chip .chip-title {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .discussion-chip .chip-delete {
        opacity: 0;
        font-size: 0.9em;
        transition: opacity var(--transition);
        padding: 2px;
        border-radius: 50%;
    }

    .discussion-chip:hover .chip-delete {
        opacity: 1;
    }

    .discussion-chip .chip-delete:hover {
        background: rgba(231,76,60,0.3);
        color: #ff8585;
    }

    /* Custom scrollbar */
    .sunny-chat-messages::-webkit-scrollbar { 
        width: 6px; 
    }
    .sunny-chat-messages::-webkit-scrollbar-track { 
        background: transparent; 
        margin: 8px 0;
    }
    .sunny-chat-messages::-webkit-scrollbar-thumb { 
        background: linear-gradient(180deg, var(--gold-border), rgba(212,175,55,0.2)); 
        border-radius: 3px;
    }
    .sunny-chat-messages::-webkit-scrollbar-thumb:hover { 
        background: var(--gold-border); 
    }

    /* ── BUBBLES ──────────────────────────────────────────── */
    .chat-bubble {
        max-width: min(85%, 600px);
        padding: 12px 16px;
        border-radius: 18px;
        line-height: 1.65;
        font-size: 0.94em;
        white-space: pre-wrap;
        word-break: break-word;
        position: relative;
        animation: bubble-in 0.35s var(--transition) both;
        will-change: transform, opacity;
    }

    @keyframes bubble-in {
        from { 
            opacity: 0; 
            transform: translateY(12px) scale(0.96); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
        }
    }

    /* Staggered animation for multiple bubbles */
    .chat-bubble:nth-child(1) { animation-delay: 0.05s; }
    .chat-bubble:nth-child(2) { animation-delay: 0.1s; }
    .chat-bubble:nth-child(3) { animation-delay: 0.15s; }

    .chat-bubble.user {
        align-self: flex-end;
        background: linear-gradient(135deg, #c9a43f 0%, var(--gold-light) 100%);
        color: #1a1600;
        font-weight: 600;
        border-bottom-right-radius: 4px;
        box-shadow: 
            0 4px 20px rgba(212,175,55,0.3),
            0 1px 2px rgba(0,0,0,0.1);
        text-shadow: 0 1px 0 rgba(255,255,255,0.2);
    }

    .chat-bubble.user::after {
        content: '';
        position: absolute;
        bottom: 0;
        right: -6px;
        width: 12px;
        height: 12px;
        background: var(--gold-light);
        clip-path: polygon(0 0, 0% 100%, 100% 100%);
    }

    .chat-bubble.sunny {
        align-self: flex-start;
        background: linear-gradient(145deg, var(--bg-card) 0%, #252a33 100%);
        border: 1px solid var(--gold-border);
        color: var(--text-main);
        border-bottom-left-radius: 4px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.2);
    }

    .chat-bubble.sunny::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: -6px;
        width: 12px;
        height: 12px;
        background: var(--bg-card);
        border-left: 1px solid var(--gold-border);
        border-bottom: 1px solid var(--gold-border);
        clip-path: polygon(100% 0, 0% 100%, 100% 100%);
    }

    .chat-bubble.sunny strong { 
        color: var(--gold-light); 
        font-weight: 600;
    }

    .chat-bubble.sunny a {
        color: var(--gold-light);
        text-decoration: none;
        border-bottom: 1px solid var(--gold-border);
        transition: border-color var(--transition);
    }

    .chat-bubble.sunny a:hover {
        border-color: var(--gold-light);
    }

    .chat-bubble.system {
        align-self: center;
        background: linear-gradient(135deg, rgba(212,175,55,0.12), rgba(212,175,55,0.05));
        border: 1px solid rgba(212,175,55,0.25);
        color: var(--gold);
        font-size: 0.82em;
        border-radius: 22px;
        padding: 8px 18px;
        font-weight: 500;
        max-width: 92%;
        text-align: center;
        animation: system-bubble-in 0.4s var(--transition) both;
    }

    @keyframes system-bubble-in {
        from { 
            opacity: 0; 
            transform: translateY(10px) scale(0.95); 
        }
        to { 
            opacity: 1; 
            transform: translateY(0) scale(1); 
        }
    }

    /* ── TYPING INDICATOR ────────────────────────────────── */
    .typing-indicator {
        align-self: flex-start;
        background: linear-gradient(145deg, var(--bg-card), #252a33);
        border: 1px solid var(--gold-border);
        border-radius: 18px 18px 18px 4px;
        padding: 12px 16px;
        display: flex; gap: 4px; align-items: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        animation: typing-in 0.3s var(--transition) both;
    }

    @keyframes typing-in {
        from { opacity: 0; transform: scale(0.9); }
        to { opacity: 1; transform: scale(1); }
    }

    .typing-indicator span {
        width: 8px; height: 8px;
        background: linear-gradient(135deg, var(--gold), var(--gold-light));
        border-radius: 50%;
        animation: typing-bounce 1.4s ease-in-out infinite both;
    }
    .typing-indicator span:nth-child(1) { animation-delay: -0.32s; }
    .typing-indicator span:nth-child(2) { animation-delay: -0.16s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0s; }

    @keyframes typing-bounce {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
        40% { transform: scale(1); opacity: 1; }
    }

    /* ── ALERTES ─────────────────────────────────────────── */
    .sunny-alertes { 
        margin-top: 10px; 
        display: flex; 
        flex-direction: column; 
        gap: 6px;
        animation: alerts-in 0.3s var(--transition) both;
    }

    @keyframes alerts-in {
        from { opacity: 0; transform: translateX(-10px); }
        to { opacity: 1; transform: translateX(0); }
    }

    .sunny-alerte {
        background: linear-gradient(90deg, rgba(212,175,55,0.15), rgba(212,175,55,0.05));
        border-left: 3px solid var(--gold);
        padding: 8px 12px;
        border-radius: 0 8px 8px 0;
        font-size: 0.85em;
        color: var(--gold-light);
        display: flex;
        align-items: center;
        gap: 6px;
        animation: alert-slide 0.3s var(--transition) both;
    }

    @keyframes alert-slide {
        from { opacity: 0; transform: translateX(-15px); }
        to { opacity: 1; transform: translateX(0); }
    }

    .sunny-alerte.haute { 
        background: linear-gradient(90deg, rgba(231,76,60,0.15), rgba(231,76,60,0.05));
        border-left-color: #e74c3c; 
        color: #ff8585; 
    }
    .sunny-alerte.moyenne { 
        background: linear-gradient(90deg, rgba(243,156,18,0.15), rgba(243,156,18,0.05));
        border-left-color: #f39c12; 
        color: #ffc266; 
    }

    .sunny-alerte::before {
        content: '⚠️';
        font-size: 1.1em;
    }

    /* ── DRAWER OVERLAY ──────────────────────────────────── */
    .sunny-drawer-overlay {
        display: none;
        position: absolute;
        inset: 0;
        background: linear-gradient(180deg, rgba(10,12,18,0.4), rgba(10,12,18,0.8));
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 20;
        opacity: 0;
        transition: opacity 0.3s var(--transition);
    }
    .sunny-drawer-overlay.active { 
        display: block; 
        opacity: 1;
    }

    /* ── DRAWERS ─────────────────────────────────────────── */
    .sunny-drawer {
        position: absolute;
        bottom: 0; left: 0; right: 0;
        background: linear-gradient(180deg, rgba(28,31,38,0.98) 0%, rgba(25,28,35,0.99) 100%);
        backdrop-filter: blur(20px);
        -webkit-backdrop-filter: blur(20px);
        border-top: 1px solid var(--gold-border);
        border-radius: var(--radius-xl) var(--radius-xl) 0 0;
        z-index: 30;
        max-height: 85%;
        overflow-y: auto;
        overflow-x: hidden;
        padding: 0 0 calc(16px + var(--safe-bottom));
        transform: translateY(100%);
        transition: transform 0.4s cubic-bezier(0.32, 0.72, 0, 1);
        box-shadow: 
            0 -20px 60px rgba(0,0,0,0.6),
            0 -1px 0 var(--gold-border),
            inset 0 1px 0 rgba(255,255,255,0.05);
        will-change: transform;
    }
    .sunny-drawer.open { 
        transform: translateY(0); 
    }

    .sunny-drawer::-webkit-scrollbar { 
        width: 5px; 
    }
    .sunny-drawer::-webkit-scrollbar-thumb { 
        background: var(--gold-border); 
        border-radius: 3px;
    }

    .sunny-drawer-handle {
        width: 40px; height: 5px;
        background: linear-gradient(90deg, transparent, var(--gold-border), transparent);
        border-radius: 3px;
        margin: 14px auto 4px;
        position: relative;
    }

    .sunny-drawer-handle::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        width: 60px;
        height: 20px;
        cursor: grab;
    }

    .sunny-drawer-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 12px 20px 14px;
        border-bottom: 1px solid var(--gold-dim);
        position: sticky;
        top: 0;
        background: linear-gradient(180deg, rgba(28,31,38,0.98), rgba(28,31,38,0.95));
        backdrop-filter: blur(10px);
        z-index: 5;
    }

    .sunny-drawer-header h4 {
        color: var(--gold-light);
        margin: 0;
        font-size: 0.9em;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1.5px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sunny-drawer-close {
        width: 32px; height: 32px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(145deg, var(--gold-dim), rgba(212,175,55,0.05));
        border: 1px solid var(--gold-border);
        border-radius: 50%;
        color: var(--gold);
        cursor: pointer;
        font-size: 0.9em;
        transition: all var(--transition);
        position: relative;
        overflow: hidden;
    }

    .sunny-drawer-close:hover { 
        background: rgba(212,175,55,0.25);
        border-color: var(--gold-light);
        transform: rotate(90deg);
    }

    .sunny-drawer-body { 
        padding: 16px 20px calc(16px + var(--safe-bottom));
    }

    /* ── DRAWER : ANALYSE ───────────────────────────────── */
    .analyse-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 10px;
    }

    .analyse-field label {
        color: var(--text-muted);
        font-size: 0.75em;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        display: block;
        margin-bottom: 5px;
    }

    .analyse-field input {
        width: 100%;
        padding: 9px 12px;
        background: var(--bg-input);
        border: 1px solid var(--gold-border);
        color: var(--text-main);
        border-radius: var(--radius-sm);
        font-size: 0.9em;
    }
    .analyse-field input:focus {
        outline: none;
        border-color: var(--gold-light);
        box-shadow: 0 0 0 3px rgba(255,215,0,0.12);
    }

    /* ── DRAWER : PRODUITS ──────────────────────────────── */
    .sunny-products-list { margin-bottom: 14px; }

    .sunny-product-item {
        padding: 10px 12px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--gold-dim);
        border-radius: var(--radius-sm);
        margin-bottom: 7px;
    }

    .product-info {
        display: flex; gap: 7px; align-items: center;
        margin-bottom: 7px; flex-wrap: wrap;
    }

    .product-cat {
        background: var(--gold-dim);
        color: var(--gold);
        padding: 2px 7px;
        border-radius: 4px;
        font-size: 0.68em;
        text-transform: uppercase;
        font-weight: 600;
    }

    .product-brand { color: var(--gold-light); font-size: 0.83em; font-weight: 700; }
    .product-name  { color: var(--text-main); font-size: 0.83em; flex: 1; }

    .product-controls {
        display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
    }

    .product-controls input,
    .product-controls select {
        padding: 5px 8px;
        background: var(--bg-input);
        border: 1px solid var(--gold-border);
        color: var(--text-main);
        border-radius: 5px;
        font-size: 0.82em;
    }
    .product-controls input { width: 58px; }

    .product-controls button {
        border: none;
        color: #fff;
        padding: 5px 9px;
        border-radius: 5px;
        cursor: pointer;
        font-size: 0.75em;
        transition: opacity var(--transition);
    }
    .product-controls button:hover { opacity: 0.85; }
    .product-controls button.save-btn { background: rgba(46,204,113,0.8); }
    .product-controls button.del-btn  { background: rgba(231,76,60,0.8); }

    .product-controls a.photo-link {
        background: var(--gold-dim);
        color: var(--gold);
        padding: 5px 7px;
        border-radius: 5px;
        font-size: 0.85em;
        text-decoration: none;
    }
    .product-controls a.photo-link:hover { background: rgba(212,175,55,0.3); }

    /* Product add form */
    .sunny-product-add-form {
        border-top: 1px solid var(--gold-dim);
        padding-top: 14px;
        margin-top: 4px;
    }

    .product-form-row {
        display: flex; gap: 8px; align-items: center;
        margin-bottom: 8px; flex-wrap: wrap;
    }

    .product-form-row select,
    .product-form-row input[type="text"],
    .product-form-row input[type="number"] {
        padding: 8px 10px;
        background: var(--bg-input);
        border: 1px solid var(--gold-border);
        color: var(--text-main);
        border-radius: var(--radius-sm);
        font-size: 0.85em;
    }

    .product-form-row input[type="file"] {
        padding: 6px;
        background: rgba(255,255,255,0.04);
        border: 1px dashed var(--gold-border);
        color: var(--text-muted);
        border-radius: var(--radius-sm);
        font-size: 0.78em;
        flex: 1;
    }

    .product-form-row textarea {
        padding: 8px 10px;
        background: var(--bg-input);
        border: 1px solid var(--gold-border);
        color: var(--text-main);
        border-radius: var(--radius-sm);
        font-size: 0.85em;
        min-height: 52px;
        resize: vertical;
        font-family: inherit;
    }

    .product-form-row .btn-add-product {
        background: linear-gradient(135deg, #c49b2a, var(--gold-light));
        border: none;
        color: #1a1600;
        padding: 9px 16px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        font-size: 0.85em;
        font-weight: 700;
        transition: box-shadow var(--transition);
    }
    .product-form-row .btn-add-product:hover {
        box-shadow: 0 4px 14px rgba(255,215,0,0.4);
    }

    .sunny-products-empty {
        color: var(--text-muted);
        font-size: 0.85em;
        font-style: italic;
        text-align: center;
        padding: 14px;
    }

    /* ── DRAWER : OPTIONS ───────────────────────────────── */
    .data-options-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }

    .data-option-item {
        display: flex;
        align-items: center;
        gap: 9px;
        padding: 10px 12px;
        background: rgba(255,255,255,0.03);
        border: 1px solid var(--gold-dim);
        border-radius: var(--radius-sm);
        cursor: pointer;
        user-select: none;
        transition: background var(--transition);
    }
    .data-option-item:hover { background: var(--gold-dim); }

    .data-option-item input[type="checkbox"] {
        accent-color: var(--gold);
        width: 15px; height: 15px;
        flex-shrink: 0;
        cursor: pointer;
    }

    .data-option-item span {
        color: var(--text-main);
        font-size: 0.85em;
    }

    /* ── INPUT AREA ─────────────────────────────────────── */
    .sunny-chat-input-area {
        background: linear-gradient(180deg, var(--bg-card) 0%, rgba(28,31,38,0.98) 100%);
        border-top: 1px solid var(--gold-border);
        padding: 12px 16px calc(16px + var(--safe-bottom));
        position: relative;
    }

    /* Quick suggestions */
    .sunny-suggestions {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        padding: 0 4px 12px;
        scrollbar-width: none;
        -ms-overflow-style: none;
        -webkit-overflow-scrolling: touch;
        mask-image: linear-gradient(90deg, transparent, black 4%, black 96%, transparent);
    }
    .sunny-suggestions::-webkit-scrollbar { display: none; }

    .sunny-suggestion-pill {
        flex-shrink: 0;
        padding: 6px 14px;
        background: linear-gradient(145deg, var(--gold-dim), rgba(212,175,55,0.08));
        border: 1px solid var(--gold-border);
        color: var(--gold);
        border-radius: 20px;
        font-size: 0.78em;
        font-weight: 600;
        cursor: pointer;
        white-space: nowrap;
        transition: all var(--transition);
        position: relative;
        overflow: hidden;
    }

    .sunny-suggestion-pill::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: radial-gradient(circle, rgba(255,215,0,0.3) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.4s, height 0.4s;
    }

    .sunny-suggestion-pill:hover {
        background: linear-gradient(145deg, rgba(212,175,55,0.25), rgba(212,175,55,0.15));
        border-color: var(--gold-light);
        box-shadow: 0 0 15px rgba(212,175,55,0.25);
        color: var(--gold-light);
        transform: translateY(-1px);
    }

    .sunny-suggestion-pill:hover::before {
        width: 100px;
        height: 100px;
    }

    .sunny-suggestion-pill:active {
        transform: translateY(0);
    }

    /* Image preview (compact) */
    .sunny-image-preview {
        display: none;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        background: linear-gradient(90deg, rgba(212,175,55,0.1), rgba(212,175,55,0.05));
        border: 1px solid var(--gold-border);
        border-radius: var(--radius-md);
        margin-bottom: 10px;
        animation: preview-in 0.3s var(--transition) both;
    }

    @keyframes preview-in {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .sunny-image-preview.active { display: flex; }

    .sunny-image-preview img {
        height: 48px;
        width: 48px;
        border-radius: 8px;
        border: 1px solid var(--gold-border);
        object-fit: cover;
        flex-shrink: 0;
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }

    .sunny-image-preview .img-meta { flex: 1; min-width: 0; }
    .sunny-image-meta-label {
        color: var(--gold-light);
        font-size: 0.82em;
        font-weight: 700;
        margin-bottom: 2px;
    }
    .sunny-image-meta-sub {
        color: var(--text-muted);
        font-size: 0.75em;
    }

    .sunny-image-remove {
        width: 26px; height: 26px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(145deg, rgba(231,76,60,0.2), rgba(231,76,60,0.1));
        border: 1px solid rgba(231,76,60,0.4);
        border-radius: 50%;
        color: #ff8585;
        cursor: pointer;
        font-size: 0.8em;
        flex-shrink: 0;
        transition: all var(--transition);
    }
    .sunny-image-remove:hover { 
        background: rgba(231,76,60,0.4);
        transform: scale(1.1);
    }

    /* Unified input row */
    .sunny-input-unified {
        display: flex;
        align-items: flex-end;
        gap: 10px;
        background: linear-gradient(145deg, rgba(35,39,48,0.8), rgba(35,39,48,0.6));
        border: 1.5px solid var(--gold-border);
        border-radius: 16px;
        padding: 8px 10px 8px 8px;
        transition: all var(--transition);
        position: relative;
    }
    .sunny-input-unified:focus-within {
        border-color: var(--gold-light);
        box-shadow: 
            0 0 0 3px rgba(212,175,55,0.15),
            0 0 20px rgba(212,175,55,0.1);
        background: linear-gradient(145deg, rgba(35,39,48,0.95), rgba(35,39,48,0.8));
    }

    /* Attachment menu button */
    .sunny-attach-btn {
        width: 40px; height: 40px;
        display: flex; align-items: center; justify-content: center;
        background: linear-gradient(145deg, var(--gold-dim), rgba(212,175,55,0.08));
        border: 1px solid var(--gold-border);
        border-radius: 12px;
        color: var(--gold);
        cursor: pointer;
        font-size: 1.2em;
        flex-shrink: 0;
        transition: all var(--transition);
        position: relative;
    }
    .sunny-attach-btn:hover { 
        background: linear-gradient(145deg, rgba(212,175,55,0.25), rgba(212,175,55,0.15));
        border-color: var(--gold-light);
        transform: scale(1.05);
    }
    .sunny-attach-btn.active {
        background: linear-gradient(145deg, rgba(212,175,55,0.3), rgba(212,175,55,0.2));
        border-color: var(--gold-light);
        box-shadow: 0 0 15px rgba(212,175,55,0.2);
    }

    /* Attachment dropdown */
    .sunny-attach-menu {
        position: absolute;
        bottom: calc(100% + 10px);
        left: 0;
        background: linear-gradient(180deg, var(--bg-card), rgba(28,31,38,0.98));
        border: 1px solid var(--gold-border);
        border-radius: var(--radius-md);
        padding: 8px;
        display: none;
        flex-direction: column;
        gap: 4px;
        z-index: 100;
        min-width: 180px;
        box-shadow: 0 12px 40px rgba(0,0,0,0.5), 0 0 0 1px rgba(212,175,55,0.1);
        animation: menu-in 0.2s var(--transition) both;
        backdrop-filter: blur(10px);
    }
    @keyframes menu-in {
        from { opacity:0; transform: translateY(10px) scale(0.95); }
        to   { opacity:1; transform: translateY(0) scale(1); }
    }
    .sunny-attach-menu.open { display: flex; }

    .attach-menu-item {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 12px;
        border-radius: var(--radius-sm);
        cursor: pointer;
        color: var(--text-main);
        font-size: 0.86em;
        font-weight: 500;
        transition: all var(--transition);
        border: 1px solid transparent;
    }
    .attach-menu-item:hover {
        background: var(--gold-dim);
        color: var(--gold-light);
        border-color: var(--gold-border);
    }
    .attach-menu-item .item-icon { 
        font-size: 1.2em;
        filter: drop-shadow(0 1px 2px rgba(0,0,0,0.2));
    }

    /* Hidden file inputs */
    .sunny-file-input { display: none; }

    /* Textarea */
    .sunny-input-unified textarea {
        flex: 1;
        background: transparent;
        border: none;
        color: var(--text-main);
        padding: 8px 6px;
        font-family: inherit;
        font-size: 0.96em;
        resize: none;
        min-height: 40px;
        max-height: 140px;
        line-height: 1.5;
        -webkit-appearance: none;
    }
    .sunny-input-unified textarea:focus { outline: none; }
    .sunny-input-unified textarea::placeholder { 
        color: var(--text-muted);
        opacity: 0.7;
    }

    /* Send button */
    .sunny-send-btn {
        width: 40px; height: 40px;
        background: linear-gradient(135deg, #c9a43f 0%, var(--gold-light) 50%, #b8942c 100%);
        color: #1a1600;
        border: none;
        border-radius: 12px;
        cursor: pointer;
        font-size: 1.1em;
        flex-shrink: 0;
        display: flex; align-items: center; justify-content: center;
        transition: all var(--transition);
        font-weight: 700;
        position: relative;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(212,175,55,0.3);
    }

    .sunny-send-btn::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        width: 0;
        height: 0;
        background: radial-gradient(circle, rgba(255,255,255,0.4) 0%, transparent 70%);
        border-radius: 50%;
        transform: translate(-50%, -50%);
        transition: width 0.4s, height 0.4s;
    }

    .sunny-send-btn:hover:not(:disabled) {
        box-shadow: 0 4px 20px rgba(255,215,0,0.5);
        transform: scale(1.08);
    }

    .sunny-send-btn:hover::after {
        width: 80px;
        height: 80px;
    }

    .sunny-send-btn:active:not(:disabled) {
        transform: scale(0.95);
    }

    .sunny-send-btn:disabled { 
        opacity: 0.3; 
        cursor: not-allowed; 
        transform: none;
        box-shadow: none;
    }

    /* ── RESPONSIVE ─────────────────────────────────────── */
    /* Tablet */
    @media (max-width: 768px) {
        .sunny-chat-wrapper {
            margin: 10px;
            border-radius: var(--radius-lg);
        }
        .sunny-chat-messages {
            height: min(55vh, 420px);
            padding: 16px;
        }
        .sunny-drawer {
            max-height: 80%;
        }
    }

    /* Mobile */
    @media (max-width: 580px) {
        .sunny-chat-wrapper {
            margin: 0;
            border-radius: 0;
            max-width: 100%;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .sunny-chat-header {
            padding: 12px 14px;
            gap: 10px;
        }

        .sunny-avatar {
            width: 40px;
            height: 40px;
            font-size: 1.2em;
        }

        .sunny-chat-header h3 {
            font-size: 0.95em;
        }

        .sunny-hdr-btn {
            width: 32px;
            height: 32px;
            font-size: 0.9em;
        }

        .sunny-chat-messages {
            flex: 1;
            height: auto;
            min-height: 250px;
            padding: 14px;
            gap: 12px;
        }

        .chat-bubble {
            max-width: 92%;
            padding: 10px 14px;
            font-size: 0.92em;
        }

        .sunny-chat-input-area {
            padding: 10px 12px calc(12px + var(--safe-bottom));
        }

        .sunny-suggestions {
            gap: 6px;
        }

        .sunny-suggestion-pill {
            padding: 5px 12px;
            font-size: 0.75em;
        }

        .sunny-input-unified {
            padding: 6px 8px 6px 6px;
        }

        .sunny-attach-btn {
            width: 36px;
            height: 36px;
        }

        .sunny-send-btn {
            width: 36px;
            height: 36px;
        }

        .sunny-input-unified textarea {
            font-size: 16px; /* Prevent zoom on iOS */
        }

        .sunny-drawer {
            max-height: 90%;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0;
        }

        .analyse-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }

        .data-options-grid {
            grid-template-columns: 1fr;
        }

        .sunny-drawer-body {
            padding: 12px 16px calc(12px + var(--safe-bottom));
        }
    }

    /* Small mobile */
    @media (max-width: 380px) {
        .sunny-chat-header h3 {
            font-size: 0.9em;
        }

        .sunny-pool-selector-wrap select {
            max-width: 140px;
            font-size: 0.75em;
        }

        .chat-bubble {
            max-width: 95%;
            padding: 9px 12px;
        }
    }

    /* Reduced motion preference */
    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: 0.01ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 0.01ms !important;
        }
    }

    /* Focus visible for accessibility */
    .sunny-chat-wrapper *:focus-visible {
        outline: 2px solid var(--gold-light);
        outline-offset: 2px;
    }

    button:focus-visible,
    select:focus-visible,
    input:focus-visible,
    textarea:focus-visible {
        outline: 2px solid var(--gold-light);
        outline-offset: 2px;
    }

    /* ── TOAST NOTIFICATIONS ─────────────────────────────── */
    .sunny-toast-container {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 8px;
        pointer-events: none;
    }

    .sunny-toast {
        background: linear-gradient(145deg, var(--bg-card), rgba(28,31,38,0.98));
        border: 1px solid var(--gold-border);
        border-radius: var(--radius-md);
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4), 0 0 0 1px rgba(212,175,55,0.1);
        animation: toast-in 0.3s var(--transition) both;
        pointer-events: auto;
        max-width: 320px;
    }

    @keyframes toast-in {
        from { opacity: 0; transform: translateX(30px); }
        to { opacity: 1; transform: translateX(0); }
    }

    @keyframes toast-out {
        from { opacity: 1; transform: translateX(0); }
        to { opacity: 0; transform: translateX(30px); }
    }

    .sunny-toast.hiding {
        animation: toast-out 0.2s var(--transition) both;
    }

    .sunny-toast-icon {
        font-size: 1.2em;
        flex-shrink: 0;
    }

    .sunny-toast-content {
        flex: 1;
        color: var(--text-main);
        font-size: 0.88em;
        line-height: 1.4;
    }

    .sunny-toast-close {
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        font-size: 1em;
        padding: 4px;
        transition: color var(--transition);
    }

    .sunny-toast-close:hover {
        color: var(--gold-light);
    }

    /* Toast types */
    .sunny-toast.success { border-color: rgba(46,204,113,0.5); }
    .sunny-toast.success .sunny-toast-icon { color: #2ecc71; }

    .sunny-toast.error { border-color: rgba(231,76,60,0.5); }
    .sunny-toast.error .sunny-toast-icon { color: #e74c3c; }

    .sunny-toast.warning { border-color: rgba(243,156,18,0.5); }
    .sunny-toast.warning .sunny-toast-icon { color: #f39c12; }

    /* ── EMPTY STATES ─────────────────────────────────────── */
    .sunny-empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
        text-align: center;
        color: var(--text-muted);
    }

    .sunny-empty-state-icon {
        font-size: 3em;
        margin-bottom: 16px;
        opacity: 0.6;
        filter: drop-shadow(0 4px 8px rgba(0,0,0,0.2));
    }

    .sunny-empty-state-text {
        font-size: 0.95em;
        max-width: 260px;
        line-height: 1.5;
    }

    /* ── LOADING STATES ──────────────────────────────────── */
    .sunny-loading-dots {
        display: flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .sunny-loading-dots span {
        width: 8px;
        height: 8px;
        background: var(--gold);
        border-radius: 50%;
        animation: loading-dot 1.4s ease-in-out infinite both;
    }

    .sunny-loading-dots span:nth-child(1) { animation-delay: -0.32s; }
    .sunny-loading-dots span:nth-child(2) { animation-delay: -0.16s; }

    @keyframes loading-dot {
        0%, 80%, 100% { transform: scale(0.6); opacity: 0.5; }
        40% { transform: scale(1); opacity: 1; }
    }
    </style>

    <!-- ===== HTML ===== -->

    <!-- Toast Container -->
    <div class="sunny-toast-container" id="sunny-toast-container"></div>

    <!-- Header -->
    <div class="sunny-chat-header" role="banner">
        <div class="sunny-avatar" aria-label="Sunny Avatar">🌞</div>
        <div class="sunny-header-info">
            <h3>Sunny — Expert Piscine</h3>
            <div class="sunny-status-row">
                <span class="sunny-status-dot"></span>
                <?php if (count($pools) > 1) : ?>
                <div class="sunny-pool-selector-wrap">
                    <select id="sunny-pool-selector">
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
            <button class="sunny-hdr-btn" onclick="sunnyNewDiscussion()" title="Nouvelle discussion">✨</button>
            <button class="sunny-hdr-btn" id="hdr-analyse-btn" onclick="sunnyOpenDrawer('analyse')" title="Mesures d'eau">📊</button>
            <button class="sunny-hdr-btn" id="hdr-products-btn" onclick="sunnyOpenDrawer('products')" title="Mes produits">🧴</button>
            <button class="sunny-hdr-btn" id="hdr-options-btn" onclick="sunnyOpenDrawer('options')" title="Options">⚙️</button>
        </div>
    </div>

    <!-- Barre de gestion des discussions -->
    <div class="sunny-discussions-bar">
        <button class="sunny-new-discussion-btn" onclick="sunnyNewDiscussion()">
            ✨ Nouvelle discussion
        </button>
        <div class="sunny-discussions-list" id="sunny-discussions-list">
            <span style="color:#666; font-size:0.8em;">Chargement...</span>
        </div>
    </div>

    <!-- Messages -->
    <div class="sunny-chat-messages" id="sunny-messages">
        <div class="chat-bubble system">Bonjour ! Je suis Sunny 🌞 Posez-moi vos questions, envoyez une 📸 photo de bandelette — j'extrais automatiquement les valeurs et pré-remplis votre analyse.</div>
        <?php if (!$has_pool): ?>
        <div class="chat-bubble system">⚠️ Vous n'avez pas encore enregistré de piscine. <a href="<?php echo home_url('/ajouter-ma-piscine'); ?>" style="color:var(--gold-light,#ffd700);">Ajoutez-la ici</a> pour des conseils personnalisés.</div>
        <?php endif; ?>
    </div>

    <!-- Input Area -->
    <div class="sunny-chat-input-area">
        <!-- Quick suggestions -->
        <div class="sunny-suggestions" id="sunny-suggestions">
            <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Mon eau est verte, que faire ?')">🟢 Eau verte</button>
            <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Mon pH est trop élevé, comment le corriger ?')">⚗️ pH élevé</button>
            <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Donne-moi mon planning d\'entretien de la semaine')">📅 Planning semaine</button>
            <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Mon chlore est insuffisant, que faire ?')">💊 Chlore faible</button>
            <button class="sunny-suggestion-pill" onclick="sunnyQuickSend('Comment hiverniser ma piscine ?')">❄️ Hivernage</button>
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

        <!-- Hidden file inputs -->
        <input type="file" id="sunny-image-water"   class="sunny-file-input" accept="image/*" capture="environment" onchange="sunnyLoadImage(this,'water')">
        <input type="file" id="sunny-image-product" class="sunny-file-input" accept="image/*" capture="environment" onchange="sunnyLoadImage(this,'product')">
        <input type="file" id="sunny-image-pool"    class="sunny-file-input" accept="image/*" capture="environment" onchange="sunnyLoadImage(this,'pool')">

        <!-- Unified input row -->
        <div class="sunny-input-unified">
            <!-- Attachment button + dropdown -->
            <div class="sunny-attach-btn" id="sunny-attach-btn" onclick="sunnyToggleAttachMenu(event)">
                ＋
                <div class="sunny-attach-menu" id="sunny-attach-menu">
                    <label class="attach-menu-item" onclick="document.getElementById('sunny-image-water').click(); sunnyCloseAttachMenu();">
                        <span class="item-icon">💧</span> Eau / Bandelette
                    </label>
                    <label class="attach-menu-item" onclick="document.getElementById('sunny-image-product').click(); sunnyCloseAttachMenu();">
                        <span class="item-icon">🧴</span> Produit
                    </label>
                    <label class="attach-menu-item" onclick="document.getElementById('sunny-image-pool').click(); sunnyCloseAttachMenu();">
                        <span class="item-icon">🏊</span> Photo piscine
                    </label>
                </div>
            </div>

            <textarea id="sunny-input"
                placeholder="Posez votre question à Sunny…"
                rows="1"
                onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sunnySend();}"
                oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>

            <button class="sunny-send-btn" id="sunny-send" onclick="sunnySend()" title="Envoyer">➤</button>
        </div>
    </div>

    <!-- ── DRAWER OVERLAY ── -->
    <div class="sunny-drawer-overlay" id="sunny-drawer-overlay" onclick="sunnyCloseDrawer()"></div>

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
            <p style="color:var(--text-muted);font-size:0.78em;margin-top:14px;">Ces valeurs seront incluses dans votre prochain message à Sunny.</p>
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
                    <select id="product-categorie" style="flex:1.5">
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
                    <select id="product-unit" style="width:90px">
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
            const CONVERSATIONS_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/chat/conversations')); ?>';

            let currentPoolId       = <?php echo $selected_pool_id; ?>;
            let currentConversationId = null;
            let imageBase64         = null;
            let currentImageType    = 'general';
            let isLoading           = false;
            let currentDrawer       = null;

            const msgsContainer = document.getElementById('sunny-messages');

            // ── GESTION DES CONVERSATIONS ─────────────────────────────
            window.sunnyLoadDiscussions = function() {
                const listEl = document.getElementById('sunny-discussions-list');
                if (!listEl) return;
                
                $.ajax({
                    url: `${CONVERSATIONS_URL}?pool_id=${currentPoolId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success) {
                            listEl.innerHTML = '';
                            if (data.data.length === 0) {
                                listEl.innerHTML = '<span style="color:#666; font-size:0.8em; padding:4px;">Aucune discussion</span>';
                                sunnyNewDiscussion(true);
                            } else {
                                data.data.forEach(conv => {
                                    const chip = document.createElement('div');
                                    chip.className = 'discussion-chip' + (conv.id == currentConversationId ? ' active' : '');
                                    chip.innerHTML = `<span class="chip-title">${escapeHtml(conv.title)}</span><span class="chip-delete" onclick="event.stopPropagation(); sunnyDeleteConversation(${conv.id})">×</span>`;
                                    chip.onclick = () => sunnySwitchConversation(conv.id);
                                    listEl.appendChild(chip);
                                });
                            }
                        }
                    },
                    error: function() {
                        listEl.innerHTML = '<span style="color:#e74c3c; font-size:0.8em;">Erreur chargement</span>';
                    }
                });
            };

            window.sunnyNewDiscussion = function(silent = false) {
                let title = 'Discussion ' + new Date().toLocaleDateString('fr-FR');
                if (!silent) {
                    title = prompt("Nom de la discussion :", title);
                    if (!title) return;
                }

                $.ajax({
                    url: CONVERSATIONS_URL,
                    method: 'POST',
                    headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' },
                    data: JSON.stringify({ pool_id: currentPoolId, title: title }),
                    success: function(data) {
                        if (data.success) {
                            currentConversationId = data.data.id;
                            sunnyLoadDiscussions();
                            sunnyClearChat();
                            if(!silent) appendBubble('✨ Nouvelle discussion démarrée.', 'system');
                        }
                    },
                    error: function() {
                        if(!silent) appendBubble('⚠️ Erreur lors de la création de la discussion.', 'system');
                    }
                });
            };

            window.sunnySwitchConversation = function(convId) {
                currentConversationId = convId;
                sunnyLoadDiscussions();
                sunnyClearChat();
                loadChatHistory(currentPoolId);
            };

            window.sunnyDeleteConversation = function(convId) {
                if (!confirm('Supprimer cette conversation et tous ses messages ?')) return;
                
                $.ajax({
                    url: `${CONVERSATIONS_URL}/${convId}`,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success) {
                            if (currentConversationId == convId) {
                                currentConversationId = null;
                                sunnyClearChat();
                            }
                            sunnyLoadDiscussions();
                            appendBubble('🗑️ Conversation supprimée.', 'system');
                        }
                    },
                    error: function() {
                        appendBubble('⚠️ Erreur lors de la suppression.', 'system');
                    }
                });
            };

            function sunnyClearChat() {
                if (msgsContainer) msgsContainer.innerHTML = '<div class="chat-bubble system">Bonjour ! Je suis Sunny 🌞 Posez-moi vos questions.</div>';
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

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

            // ── ATTACH MENU ──────────────────────────────────────────
            window.sunnyToggleAttachMenu = function(e) {
                e.stopPropagation();
                const menu = document.getElementById('sunny-attach-menu');
                const btn = document.getElementById('sunny-attach-btn');
                const isOpen = menu.classList.toggle('open');
                if (btn) btn.classList.toggle('active', isOpen);
            };

            window.sunnyCloseAttachMenu = function() {
                const menu = document.getElementById('sunny-attach-menu');
                const btn = document.getElementById('sunny-attach-btn');
                menu.classList.remove('open');
                if (btn) btn.classList.remove('active');
            };

            document.addEventListener('click', function(e) {
                if (!e.target.closest('#sunny-attach-btn')) sunnyCloseAttachMenu();
            });

            // ── HISTORY ─────────────────────────────────────────────
            function loadChatHistory(poolId) {
                if (!msgsContainer) return;
                msgsContainer.innerHTML = '<div class="chat-bubble system">Chargement...</div>';
                $.ajax({
                    url: `${HISTORY_URL}?pool_id=${poolId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        msgsContainer.innerHTML = '';
                        if (data.success && data.data.length > 0) {
                            data.data.reverse().forEach(function(msg) {
                                appendBubble(msg.message, 'user');
                                appendBubble(formatResponse(msg.response), 'sunny', true);
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

            // Pool selector change
            $(document).on('change', '#sunny-pool-selector', function() {
                const newPoolId = parseInt($(this).val());
                if (newPoolId === currentPoolId) return;
                currentPoolId = newPoolId;
                loadChatHistory(currentPoolId);
            });

            setTimeout(function() { loadChatHistory(currentPoolId); }, 100);

            // ── PUBLIC FUNCTIONS ─────────────────────────────────────
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
                sendToSunny(message, imageBase64, analyse);
            };

            window.sunnyQuickSend = function(msg) {
                document.getElementById('sunny-input').value = msg;
                sunnySend();
            };

            window.sunnyLoadImage = function(input, type) {
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
                    currentImageType = type || 'general';

                    const labelMap = { water:'💧 Eau / Bandelette', product:'🧴 Produit', pool:'🏊 Piscine', general:'📷 Photo' };
                    const subMap   = { water:'Analyse de bandelette', product:'Identification produit', pool:'Aperçu piscine', general:'Image jointe' };

                    document.getElementById('img-preview-thumb').src = imageBase64;
                    document.getElementById('img-preview-label').textContent = labelMap[currentImageType] || '📷 Photo';
                    document.getElementById('img-preview-sub').textContent   = subMap[currentImageType] || 'Image jointe';
                    document.getElementById('sunny-image-preview-bar').classList.add('active');
                };
                reader.readAsDataURL(file);
            };

            window.sunnyRemoveImage = function() {
                imageBase64      = null;
                currentImageType = 'general';
                document.getElementById('sunny-image-preview-bar').classList.remove('active');
                document.getElementById('img-preview-thumb').src = '';
                ['sunny-image-water','sunny-image-product','sunny-image-pool'].forEach(function(id) {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });
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
                                removeTyping(typingEl);
                                appendBubble(formatResponse(data.response), 'sunny', true);
                                if (data.alertes && data.alertes.length) appendAlertes(data.alertes);
                                if (data.score_eau !== null && data.score_eau !== undefined) appendScoreEau(data.score_eau);
                                if (data.analyse_extraite) prefillAnalyse(data.analyse_extraite);
                                sunnyRemoveImage();
                                isLoading = false;
                                document.getElementById('sunny-send').disabled = false;
                            } else {
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
                            appendBubble(formatResponse(data.response), 'sunny', true);
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
                if (isHTML) div.innerHTML = text;
                else        div.textContent = text;
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
                return text
                    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
                    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
                    .replace(/\n/g,'<br>');
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
                    sunnyCloseAttachMenu();
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

            // Initialize
            console.log('[Sunny Chat] UX/UI Enhanced v2.0 initialized');
        });
    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}
