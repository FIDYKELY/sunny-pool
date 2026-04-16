<?php
/**
 * Shortcode [sunny_chat] — Interface chat Sunny Pool
 * 
 *
 * Usage sur une page WordPress : [sunny_chat]
 */

// ========== SHORTCODE CHAT SUNNY ==========
add_shortcode('sunny_chat', 'sunny_chat_shortcode');

function sunny_chat_shortcode() {
    if (!is_user_logged_in()) {
        return '<p style="color:#d4af37;">Veuillez vous <a href="' . wp_login_url(get_permalink()) . '" style="color:#ffd700;">connecter</a> pour parler à Sunny.</p>';
    }

    // Récupérer TOUTES les piscines de l'utilisateur
    $user_id = get_current_user_id();
    $pools   = get_posts([
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [['key' => 'proprietaire', 'value' => $user_id, 'compare' => '=']]
    ]);

    $has_pool    = !empty($pools);
    $selected_pool_id = isset($_GET['pool_id']) ? intval($_GET['pool_id']) : ( !empty($pools) ? $pools[0]->ID : 0 );
    
    // Vérifier que la piscine sélectionnée appartient à l'utilisateur
    $pool_valid = false;
    $selected_pool = null;
    foreach ($pools as $pool) {
        if ($pool->ID == $selected_pool_id) {
            $pool_valid = true;
            $selected_pool = $pool;
            break;
        }
    }
    if (!$pool_valid && !empty($pools)) {
        $selected_pool_id = $pools[0]->ID;
        $selected_pool = $pools[0];
    }
    
    $pool_volume = $selected_pool ? get_field('volume', $selected_pool->ID) : null;
    $pool_titre  = $selected_pool ? $selected_pool->post_title : 'votre piscine';

    // URL de l'API REST WP (sera utilisée par le JS)
    $api_url = esc_url(rest_url('sunny-pool/v1/chat'));
    $nonce   = wp_create_nonce('wp_rest');

    ob_start();
    ?>
    <div class="sunny-chat-wrapper">

        <!-- ===== STYLES ===== -->
        <style>
        .sunny-chat-wrapper {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 820px;
            margin: 0 auto;
        }

        /* En-tête */
        .sunny-chat-header {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 2px solid #d4af37;
            border-radius: 15px 15px 0 0;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .sunny-avatar {
            width: 46px; height: 46px;
            background: linear-gradient(135deg, #d4af37, #ffd700);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.6em; flex-shrink: 0;
        }
        .sunny-chat-header h3 {
            color: #d4af37; margin: 0;
            font-size: 1.15em; text-transform: uppercase; letter-spacing: 1px;
        }
        .sunny-chat-header p { color: #aaa; margin: 2px 0 0; font-size: 0.85em; }

        /* Zone messages */
        .sunny-chat-messages {
            background: #1a1a1a;
            border-left: 2px solid #d4af37;
            border-right: 2px solid #d4af37;
            height: 420px;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .sunny-chat-messages::-webkit-scrollbar { width: 6px; }
        .sunny-chat-messages::-webkit-scrollbar-track { background: #1a1a1a; }
        .sunny-chat-messages::-webkit-scrollbar-thumb { background: #d4af37; border-radius: 3px; }

        /* Bulles */
        .chat-bubble {
            max-width: 78%;
            padding: 12px 16px;
            border-radius: 14px;
            line-height: 1.55;
            font-size: 0.95em;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .chat-bubble.user {
            align-self: flex-end;
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #1a1a1a;
            border-bottom-right-radius: 3px;
            font-weight: 500;
        }
        .chat-bubble.sunny {
            align-self: flex-start;
            background: #2d2d2d;
            border: 1px solid rgba(212,175,55,0.35);
            color: #f0f0f0;
            border-bottom-left-radius: 3px;
        }
        .chat-bubble.sunny strong { color: #ffd700; }
        .chat-bubble.system {
            align-self: center;
            background: rgba(212,175,55,0.12);
            border: 1px solid rgba(212,175,55,0.3);
            color: #d4af37;
            font-size: 0.82em;
            border-radius: 20px;
            padding: 6px 14px;
            font-style: italic;
        }
        .typing-indicator {
            align-self: flex-start;
            background: #2d2d2d;
            border: 1px solid rgba(212,175,55,0.35);
            border-radius: 14px 14px 14px 3px;
            padding: 10px 16px;
            display: flex; gap: 5px; align-items: center;
        }
        .typing-indicator span {
            width: 7px; height: 7px;
            background: #d4af37; border-radius: 50%;
            animation: bounce 1.2s infinite;
        }
        .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
        .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }
        @keyframes bounce { 0%,60%,100%{transform:translateY(0)} 30%{transform:translateY(-6px)} }

        /* Alertes dans la réponse */
        .sunny-alertes {
            margin-top: 10px;
            display: flex; flex-direction: column; gap: 6px;
        }
        .sunny-alerte {
            background: rgba(212,175,55,0.15);
            border-left: 3px solid #d4af37;
            padding: 6px 10px;
            border-radius: 0 6px 6px 0;
            font-size: 0.85em; color: #ffd700;
        }
        .sunny-alerte.haute { border-left-color: #e74c3c; color: #ff6b6b; }
        .sunny-alerte.moyenne { border-left-color: #f39c12; color: #ffa94d; }

        /* Formulaire analyse */
        .sunny-analyse-panel {
            background: rgba(212,175,55,0.06);
            border: 1px solid rgba(212,175,55,0.25);
            border-radius: 10px;
            padding: 14px 16px;
            margin: 0 20px 12px;
            display: none;
        }
        .sunny-analyse-panel.open { display: block; }
        .sunny-analyse-panel h4 { color: #d4af37; margin: 0 0 10px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        .analyse-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 8px;
        }
        .analyse-grid label { color: #aaa; font-size: 0.78em; display: block; margin-bottom: 2px; }
        .analyse-grid input {
            width: 100%; padding: 7px 10px;
            background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; border-radius: 6px; font-size: 0.88em;
            box-sizing: border-box;
        }
        .analyse-grid input:focus { outline: none; border-color: #ffd700; box-shadow: 0 0 6px rgba(255,215,0,0.3); }

        /* Panneau produits */
        .sunny-products-panel {
            background: rgba(212,175,55,0.06);
            border: 1px solid rgba(212,175,55,0.25);
            border-radius: 10px;
            padding: 14px 16px;
            margin: 0 20px 12px;
            display: none;
            max-height: 450px;
            overflow-y: auto;
        }
        .sunny-products-panel.open { display: block; }
        .sunny-products-panel h4 { color: #d4af37; margin: 0 0 10px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 1px; }
        .sunny-products-list { margin-bottom: 12px; }
        .sunny-product-item {
            display: flex; flex-direction: column;
            padding: 8px 10px;
            background: rgba(45,45,45,0.6);
            border: 1px solid rgba(212,175,55,0.2);
            border-radius: 6px;
            margin-bottom: 6px;
        }
        .sunny-product-item .product-info {
            display: flex; gap: 8px; align-items: center;
            margin-bottom: 6px; flex-wrap: wrap;
        }
        .sunny-product-item .product-cat {
            background: rgba(212,175,55,0.2);
            color: #d4af37;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.7em;
            text-transform: uppercase;
        }
        .sunny-product-item .product-brand {
            color: #ffd700;
            font-size: 0.85em;
            font-weight: 600;
        }
        .sunny-product-item .product-name {
            color: #f0f0f0;
            font-size: 0.85em;
            flex: 1;
        }
        .sunny-product-item .product-controls {
            display: flex; gap: 6px; align-items: center; flex-wrap: wrap;
        }
        .sunny-product-item input {
            width: 55px; padding: 5px 8px;
            background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; border-radius: 4px; font-size: 0.85em;
        }
        .sunny-product-item select {
            padding: 5px 8px;
            background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; border-radius: 4px; font-size: 0.85em;
        }
        .sunny-product-item button {
            background: rgba(231,76,60,0.8); border: none;
            color: #fff; padding: 4px 8px; border-radius: 4px;
            cursor: pointer; font-size: 0.75em;
        }
        .sunny-product-item button:hover { background: #e74c3c; }
        .sunny-product-item button.save-btn {
            background: rgba(46,204,113,0.8);
        }
        .sunny-product-item button.save-btn:hover { background: #2ecc71; }
        .sunny-product-item a.photo-link {
            background: rgba(212,175,55,0.3);
            color: #d4af37;
            padding: 4px 6px;
            border-radius: 4px;
            font-size: 0.85em;
            text-decoration: none;
        }
        .sunny-product-item a.photo-link:hover {
            background: rgba(212,175,55,0.5);
            color: #ffd700;
        }

        /* Formulaire ajout produit */
        .sunny-product-add-form {
            border-top: 1px solid rgba(212,175,55,0.2);
            padding-top: 12px;
        }
        .product-form-row {
            display: flex; gap: 8px; align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .product-form-row:last-child { margin-bottom: 0; }
        .product-form-row select,
        .product-form-row input[type="text"],
        .product-form-row input[type="number"] {
            padding: 7px 10px;
            background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; border-radius: 6px; font-size: 0.85em;
        }
        .product-form-row input[type="file"] {
            padding: 5px;
            background: rgba(45,45,45,0.6);
            border: 1px dashed rgba(212,175,55,0.4);
            color: #aaa; border-radius: 6px;
            font-size: 0.8em; flex: 1;
        }
        .product-form-row textarea {
            padding: 7px 10px;
            background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; border-radius: 6px; font-size: 0.85em;
            min-height: 50px; resize: vertical;
            font-family: inherit;
        }
        .product-form-row button {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            border: none; color: #1a1a1a;
            padding: 8px 14px; border-radius: 6px;
            cursor: pointer; font-size: 0.85em; font-weight: 600;
        }
        .product-form-row button:hover { box-shadow: 0 2px 8px rgba(255,215,0,0.4); }

        .sunny-products-empty {
            color: #888; font-size: 0.85em; font-style: italic;
            text-align: center; padding: 12px;
        }

        /* Zone saisie */
        .sunny-chat-input-area {
            background: #1a1a1a;
            border: 2px solid #d4af37;
            border-top: 1px solid rgba(212,175,55,0.4);
            border-radius: 0 0 15px 15px;
            padding: 16px 20px;
        }

        /* Barre d'outils */
        .sunny-toolbar {
            display: flex; gap: 8px; margin-bottom: 10px; align-items: center; flex-wrap: wrap;
        }
        .sunny-tool-btn {
            background: rgba(212,175,55,0.12);
            border: 1px solid rgba(212,175,55,0.4);
            color: #d4af37; padding: 5px 11px;
            border-radius: 20px; cursor: pointer;
            font-size: 0.8em; transition: all 0.2s ease;
            white-space: nowrap;
        }
        .sunny-tool-btn:hover, .sunny-tool-btn.active {
            background: rgba(212,175,55,0.25); border-color: #ffd700; color: #ffd700;
        }
        .sunny-tool-btn input[type="file"] { display: none; }
        .image-preview-zone {
            display: none;
            margin-bottom: 10px;
            position: relative; width: fit-content;
        }
        .image-preview-zone img {
            max-height: 80px; border-radius: 8px;
            border: 2px solid #d4af37;
        }
        .image-preview-zone .remove-img {
            position: absolute; top: -6px; right: -6px;
            background: #e74c3c; color: #fff;
            border: none; border-radius: 50%;
            width: 20px; height: 20px; font-size: 0.75em;
            cursor: pointer; display: flex; align-items: center; justify-content: center;
        }

        /* Input message */
        .sunny-input-row { display: flex; gap: 10px; align-items: flex-end; }
        .sunny-chat-input-area textarea {
            flex: 1; background: #2d2d2d; border: 1px solid #d4af37;
            color: #fff; padding: 11px 14px; border-radius: 10px;
            font-family: inherit; font-size: 0.95em; resize: none;
            min-height: 44px; max-height: 120px; line-height: 1.4;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .sunny-chat-input-area textarea:focus {
            outline: none; border-color: #ffd700; box-shadow: 0 0 8px rgba(255,215,0,0.25);
        }
        .sunny-send-btn {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #1a1a1a; border: none;
            width: 46px; height: 46px; border-radius: 50%;
            cursor: pointer; font-size: 1.2em;
            transition: all 0.3s ease; flex-shrink: 0;
            display: flex; align-items: center; justify-content: center;
        }
        .sunny-send-btn:hover:not(:disabled) { transform: scale(1.08); box-shadow: 0 4px 15px rgba(255,215,0,0.4); }
        .sunny-send-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Responsive */
        @media(max-width: 560px) {
            .sunny-chat-messages { height: 320px; }
            .analyse-grid { grid-template-columns: repeat(2, 1fr); }
            .chat-bubble { max-width: 90%; }
        }
        </style>

        <!-- ===== HTML ===== -->
        <div class="sunny-chat-header">
            <div class="sunny-avatar">🌞</div>
            <div style="flex:1;">
                <h3>Sunny — Expert Piscine</h3>
                <?php if (count($pools) > 1) : ?>
                <select id="sunny-pool-selector" style="background:#2d2d2d; color:#d4af37; border:1px solid #d4af37; border-radius:6px; padding:4px 8px; margin-top:4px; width:100%;">
                    <?php foreach ($pools as $pool) : 
                        $vol = get_field('volume', $pool->ID);
                    ?>
                        <option value="<?php echo $pool->ID; ?>" <?php echo $selected_pool_id == $pool->ID ? 'selected' : ''; ?>>
                            <?php echo esc_html($pool->post_title); ?><?php echo $vol ? ' (' . $vol . ' m³)' : ''; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php else : ?>
                    <p><?php echo $pools ? esc_html($pool_titre) . ($pool_volume ? ' (' . $pool_volume . ' m³)' : '') : 'Aucune piscine enregistrée'; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zone d'analyse optionnelle -->
        <div id="sunny-analyse-panel" class="sunny-analyse-panel">
            <h4>📊 Saisir mes mesures d'eau</h4>
            <div class="analyse-grid">
                <div><label>pH</label><input type="number" id="ana-ph" step="0.1" min="0" max="14" placeholder="ex: 7.4"></div>
                <div><label>Chlore (mg/L)</label><input type="number" id="ana-chlore" step="0.1" min="0" placeholder="ex: 1.5"></div>
                <div><label>TAC (mg/L)</label><input type="number" id="ana-tac" step="1" min="0" placeholder="ex: 100"></div>
                <div><label>Stabilisant (mg/L)</label><input type="number" id="ana-stabilisant" step="1" min="0" placeholder="ex: 30"></div>
                <div><label>Température (°C)</label><input type="number" id="ana-temperature" step="0.5" min="0" max="45" placeholder="ex: 26"></div>
            </div>
        </div>

        <!-- Zone de gestion des produits -->
        <div id="sunny-products-panel" class="sunny-products-panel">
            <h4>🧴 Mes produits d'entretien</h4>
            <div id="sunny-products-list" class="sunny-products-list">
                <div class="sunny-products-empty">Chargement des produits...</div>
            </div>

            <!-- Formulaire d'ajout de produit -->
            <div class="sunny-product-add-form">
                <div class="product-form-row">
                    <select id="product-categorie">
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
                    <input type="text" id="product-marque" placeholder="Marque (ex: HTH)" style="flex: 1;">
                    <input type="text" id="product-nom" placeholder="Nom du produit" style="flex: 2;">
                </div>
                <div class="product-form-row">
                    <input type="number" id="product-quantity" placeholder="Qté" step="0.1" min="0" style="width: 80px;">
                    <select id="product-unit" style="width: 90px;">
                        <option value="L">L</option>
                        <option value="kg">kg</option>
                        <option value="g">g</option>
                        <option value="ml">ml</option>
                        <option value="unités">unités</option>
                    </select>
                    <button onclick="sunnyAddProduct()" style="flex: 1;">➕ Ajouter</button>
                </div>
                <div class="product-form-row">
                    <input type="file" id="product-photo-face" accept="image/*" capture="environment" style="flex: 1;">
                    <input type="file" id="product-photo-notice" accept="image/*" style="flex: 1;">
                </div>
                <div class="product-form-row">
                    <textarea id="product-commentaire" placeholder="Commentaire (optionnel)..." rows="2" style="flex: 1;"></textarea>
                </div>
            </div>
        </div>

        <!-- Preview image -->
        <div id="image-preview-zone" class="image-preview-zone" style="margin: 0 20px 4px;">
            <img id="image-preview" src="" alt="Aperçu">
            <button class="remove-img" onclick="sunnyRemoveImage()" title="Retirer l'image">✕</button>
        </div>

        <div class="sunny-chat-messages" id="sunny-messages">
            <div class="chat-bubble system">Bonjour ! Je suis Sunny 🌞 Posez-moi vos questions, envoyez une 📸 photo de bandelette ou d'eau — j'extrais automatiquement les valeurs et pré-remplis votre analyse.</div>
            <?php if (!$has_pool): ?>
            <div class="chat-bubble system">⚠️ Vous n'avez pas encore enregistré de piscine. <a href="<?php echo home_url('/ajouter-ma-piscine'); ?>" style="color:#ffd700;">Ajoutez-la ici</a> pour des conseils personnalisés.</div>
            <?php endif; ?>
        </div>

        <div class="sunny-chat-input-area">
            <div class="sunny-toolbar">
                <label class="sunny-tool-btn" title="Envoyez une bandelette, un écran de testeur ou une photo de votre eau — Sunny extraira les valeurs automatiquement">
                    📸 Bandelette / Photo
                    <input type="file" id="sunny-image-input" accept="image/*" capture="environment" onchange="sunnyLoadImage(this)">
                </label>
                <button class="sunny-tool-btn" id="toggle-analyse-btn" onclick="sunnyToggleAnalyse()">
                    📊 Saisir mesures
                </button>
                <button class="sunny-tool-btn" id="toggle-products-btn" onclick="sunnyToggleProducts()">
                    🧴 Mes produits
                </button>
                <button class="sunny-tool-btn" onclick="sunnyQuickSend('Mon eau est verte, que faire ?')">🟢 Eau verte</button>
                <button class="sunny-tool-btn" onclick="sunnyQuickSend('Mon pH est élevé, que faire ?')">⚗️ pH élevé</button>
                <button class="sunny-tool-btn" onclick="sunnyQuickSend('Donne-moi mon planning d\'entretien de la semaine')">📅 Planning</button>
            </div>
            <div class="sunny-input-row">
                <textarea id="sunny-input" placeholder="Posez votre question à Sunny…" rows="1"
                    onkeydown="if(event.key==='Enter'&&!event.shiftKey){event.preventDefault();sunnySend();}"
                    oninput="this.style.height='auto';this.style.height=Math.min(this.scrollHeight,120)+'px'"></textarea>
                <button class="sunny-send-btn" id="sunny-send" onclick="sunnySend()" title="Envoyer">➤</button>
            </div>
        </div>

    </div><!-- .sunny-chat-wrapper -->

    <!-- ===== JAVASCRIPT ===== -->
    <script>
    // Utiliser jQuery pour éviter les conflits et supporter Chosen
    (function($) {
        "use strict";

        $(document).ready(function() {
            console.log('[Sunny Chat] Initialisation avec jQuery/Chosen support...');

            // Config
            const API_URL      = '<?php echo $api_url; ?>';
            const CALLBACK_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/chat-callback')); ?>';
            const NONCE        = '<?php echo $nonce; ?>';
            const HISTORY_URL  = '<?php echo esc_url(rest_url('sunny-pool/v1/chat/history')); ?>';
            const PRODUCTS_URL = '<?php echo esc_url(rest_url('sunny-pool/v1/pool')); ?>';

            let currentPoolId  = <?php echo $selected_pool_id; ?>;
            let imageBase64    = null;
            let analyseOpen    = false;
            let productsOpen   = false;
            let isLoading      = false;

            // Références DOM (natif pour compatibilité)
            const msgsContainer = document.getElementById('sunny-messages');
            const poolTitleEl = document.querySelector('.sunny-chat-header h3');

            console.log('[Sunny Chat] msgsContainer trouvé:', !!msgsContainer);
            console.log('[Sunny Chat] poolSelector trouvé:', $('#sunny-pool-selector').length > 0);

            // Charger l'historique au démarrage
            function loadChatHistory(poolId) {
                console.log('[Sunny Chat] Chargement historique pool:', poolId);
                if (!msgsContainer) {
                    console.error('[Sunny Chat] msgsContainer non trouvé!');
                    return;
                }

                msgsContainer.innerHTML = '<div class="chat-bubble system">Chargement...</div>';

                $.ajax({
                    url: `${HISTORY_URL}?pool_id=${poolId}`,
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        console.log('[Sunny Chat] Historique reçu:', data);
                        msgsContainer.innerHTML = '';

                        if (data.success && data.data.length > 0) {
                            // Afficher les messages du plus ancien au plus récent
                            data.data.reverse().forEach(function(msg) {
                                appendBubble(msg.message, 'user');
                                appendBubble(msg.response, 'sunny', true);
                            });
                        } else {
                            // Message de bienvenue par défaut
                            appendBubble('Bonjour ! Je suis Sunny, votre assistant piscine. Posez-moi vos questions sur cette piscine 🏊', 'system');
                            <?php if (!$has_pool): ?>
                            appendBubble('⚠️ Vous n\'avez pas encore enregistré de piscine. <a href="<?php echo home_url('/ajouter-ma-piscine'); ?>" style="color:#ffd700;">Ajoutez-la ici</a> pour des conseils personnalisés.', 'system');
                            <?php endif; ?>
                        }
                    },
                    error: function(err) {
                        console.error('[Sunny Chat] Erreur chargement historique:', err);
                        msgsContainer.innerHTML = '';
                        appendBubble('Bonjour ! Je suis Sunny, votre assistant piscine 🏊', 'system');
                    }
                });
            }

            // Gestion du changement de piscine - Compatible Chosen/jQuery
            // Utilisation de la délégation sur document pour capturer l'événement même si Chosen modifie le DOM
            $(document).on('change', '#sunny-pool-selector', function(e) {
                const newPoolId = parseInt($(this).val());
                console.log('[Sunny Chat] Événement change détecté! Nouveau pool:', newPoolId, 'Ancien pool:', currentPoolId);

                if (newPoolId === currentPoolId) {
                    console.log('[Sunny Chat] Même piscine, ignoré');
                    return;
                }

                currentPoolId = newPoolId;

                // Mettre à jour le titre affiché
                if (poolTitleEl) {
                    const selectedText = $(this).find('option:selected').text();
                    const poolName = selectedText.split('(')[0].trim();
                    poolTitleEl.textContent = poolName;
                    console.log('[Sunny Chat] Titre mis à jour:', poolName);
                }

                // Recharger l'historique
                loadChatHistory(currentPoolId);
            });

            // Déclencher l'événement change au chargement (pour Chosen)
            setTimeout(function() {
                console.log('[Sunny Chat] Chargement initial pool:', currentPoolId);
                loadChatHistory(currentPoolId);
            }, 100);

            // === Fonctions exposées globalement ===
            window.sunnySend = function() {
                if (isLoading) return;
                const input   = document.getElementById('sunny-input');
                const message = input.value.trim();
                if (!message && !imageBase64) return;

                // Afficher la bulle utilisateur
                if (message) appendBubble(message, 'user');
                if (imageBase64) appendBubble('📸 Photo envoyée', 'user');
                input.value = '';
                input.style.height = 'auto';

                // Construire l'analyse si des champs sont remplis
                const analyse = buildAnalyse();

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
                    // On garde le dataURL complet (avec préfixe "data:image/...;base64,")
                    // Le nettoyage pour n8n se fait dans sendToSunny
                    imageBase64 = e.target.result;
                    document.getElementById('image-preview').src = imageBase64;
                    document.getElementById('image-preview-zone').style.display = 'block';
                    console.log('[Sunny] Image chargée :', Math.round(imageBase64.length * 0.75 / 1024) + ' Ko');
                };
                reader.readAsDataURL(file);
            };

            window.sunnyRemoveImage = function() {
                imageBase64 = null;
                document.getElementById('image-preview-zone').style.display = 'none';
                document.getElementById('image-preview').src = '';
                document.getElementById('sunny-image-input').value = '';
            };

            window.sunnyToggleAnalyse = function() {
                analyseOpen = !analyseOpen;
                const panel = document.getElementById('sunny-analyse-panel');
                const btn   = document.getElementById('toggle-analyse-btn');
                if (panel) {
                    panel.style.display = analyseOpen ? 'flex' : 'none';
                    btn.textContent = analyseOpen ? '📊 Masquer Analyse' : '📊 Analyse Eau';
                }
            };

            window.sunnyToggleProducts = function() {
                productsOpen = !productsOpen;
                const panel = document.getElementById('sunny-products-panel');
                const btn   = document.getElementById('toggle-products-btn');
                if (panel) {
                    panel.style.display = productsOpen ? 'block' : 'none';
                    btn.textContent = productsOpen ? '🧴 Masquer Produits' : '🧴 Mes produits';
                    if (productsOpen) {
                        loadProducts();
                    }
                }
            };

            function loadProducts() {
                const listContainer = document.getElementById('sunny-products-list');
                if (!listContainer) return;

                listContainer.innerHTML = '<div class="sunny-products-empty">Chargement...</div>';

                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success && data.data && data.data.length > 0) {
                            renderProductsList(data.data);
                        } else {
                            listContainer.innerHTML = '<div class="sunny-products-empty">Aucun produit enregistré. Ajoutez votre premier produit ci-dessous !</div>';
                        }
                    },
                    error: function() {
                        listContainer.innerHTML = '<div class="sunny-products-empty">Erreur lors du chargement des produits.</div>';
                    }
                });
            }

            const productCategories = {
                'chlore_choc': 'Chlore choc',
                'chlore_lent': 'Chlore lent',
                'ph_plus': 'pH +',
                'ph_moins': 'pH -',
                'anti_algues': 'Anti-algues',
                'clarifiant': 'Clarifiant',
                'floculant': 'Floculant',
                'sequestrant_calcaire': 'Séquestrant calcaire',
                'sequestrant_metaux': 'Séquestrant métaux'
            };

            // Convertir un fichier en base64
            function fileToBase64(file) {
                return new Promise((resolve, reject) => {
                    if (!file) {
                        resolve('');
                        return;
                    }
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });
            }

            function renderProductsList(products) {
                const listContainer = document.getElementById('sunny-products-list');
                if (!listContainer) return;

                let html = '';
                products.forEach(function(product) {
                    const productId = product.id;
                    const categorie = productCategories[product.categorie] || product.categorie || '-';
                    const marque = product.marque || '-';
                    const nom = product.nom_produit || '-';
                    const quantity = product.quantite || 0;
                    const unit = product.unite || 'L';
                    const photoFace = product.photo_face || '';
                    const photoNotice = product.photo_notice || '';

                    html += '<div class="sunny-product-item" data-product-id="' + productId + '">';
                    html += '<div class="product-info">';
                    html += '<span class="product-cat">' + categorie + '</span>';
                    html += '<span class="product-brand">' + marque + '</span>';
                    html += '<span class="product-name">' + nom + '</span>';
                    html += '</div>';
                    html += '<div class="product-controls">';
                    html += '<input type="number" class="product-qty" data-id="' + productId + '" value="' + quantity + '" step="0.1" min="0">';
                    html += '<select class="product-unit" data-id="' + productId + '">';
                    const units = ['L', 'kg', 'g', 'ml', 'unités'];
                    units.forEach(function(u) {
                        html += '<option value="' + u + '"' + (unit === u ? ' selected' : '') + '>' + u + '</option>';
                    });
                    html += '</select>';
                    html += '<button class="save-btn" onclick="sunnyUpdateProduct(\'' + productId + '\')" title="Sauvegarder">💾</button>';
                    if (photoFace) {
                        html += '<a href="' + photoFace + '" target="_blank" title="Photo face" class="photo-link">📷</a>';
                    }
                    if (photoNotice) {
                        html += '<a href="' + photoNotice + '" target="_blank" title="Photo notice" class="photo-link">📄</a>';
                    }
                    html += '<button onclick="sunnyDeleteProduct(\'' + productId + '\')" title="Supprimer">🗑️</button>';
                    html += '</div>';
                    html += '</div>';
                });

                listContainer.innerHTML = html;
            }

            window.sunnyAddProduct = async function() {
                const categorieSelect = document.getElementById('product-categorie');
                const marqueInput = document.getElementById('product-marque');
                const nomInput = document.getElementById('product-nom');
                const qtyInput = document.getElementById('product-quantity');
                const unitSelect = document.getElementById('product-unit');
                const commentaireInput = document.getElementById('product-commentaire');
                const photoFaceInput = document.getElementById('product-photo-face');
                const photoNoticeInput = document.getElementById('product-photo-notice');

                const categorie = categorieSelect.value;
                const marque = marqueInput.value.trim();
                const nom = nomInput.value.trim();

                if (!categorie) {
                    alert('Veuillez sélectionner une catégorie');
                    return;
                }
                if (!marque) {
                    alert('Veuillez saisir la marque');
                    return;
                }
                if (!nom) {
                    alert('Veuillez saisir le nom du produit');
                    return;
                }

                const quantite = parseFloat(qtyInput.value) || 0;
                const unite = unitSelect.value;
                const commentaire = commentaireInput.value.trim();

                // Conversion des photos en base64
                let photoFaceBase64 = '';
                let photoNoticeBase64 = '';

                try {
                    if (photoFaceInput.files[0]) {
                        photoFaceBase64 = await fileToBase64(photoFaceInput.files[0]);
                    }
                    if (photoNoticeInput.files[0]) {
                        photoNoticeBase64 = await fileToBase64(photoNoticeInput.files[0]);
                    }
                } catch (e) {
                    console.error('Erreur conversion image:', e);
                }

                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products',
                    method: 'POST',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({
                        categorie: categorie,
                        marque: marque,
                        nom_produit: nom,
                        quantite: quantite,
                        unite: unite,
                        commentaire: commentaire,
                        photo_face_base64: photoFaceBase64,
                        photo_notice_base64: photoNoticeBase64
                    }),
                    success: function(data) {
                        if (data.success) {
                            // Réinitialiser le formulaire
                            categorieSelect.value = '';
                            marqueInput.value = '';
                            nomInput.value = '';
                            qtyInput.value = '';
                            unitSelect.value = 'L';
                            commentaireInput.value = '';
                            photoFaceInput.value = '';
                            photoNoticeInput.value = '';
                            // Recharger la liste
                            loadProducts();
                            appendBubble('✅ Produit ajouté : ' + marque + ' ' + nom, 'system');
                        } else {
                            alert(data.message || 'Erreur lors de l\'ajout du produit');
                        }
                    },
                    error: function(xhr) {
                        alert('Erreur de connexion lors de l\'ajout du produit');
                    }
                });
            };

            window.sunnyUpdateProduct = function(productId) {
                const qtyInput = document.querySelector('.product-qty[data-id="' + productId + '"]');
                const unitSelect = document.querySelector('.product-unit[data-id="' + productId + '"]');

                if (!qtyInput || !unitSelect) return;

                const quantite = parseFloat(qtyInput.value) || 0;
                const unite = unitSelect.value;

                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products/' + productId,
                    method: 'PUT',
                    contentType: 'application/json',
                    headers: { 'X-WP-Nonce': NONCE },
                    data: JSON.stringify({
                        quantite: quantite,
                        unite: unite
                    }),
                    success: function(data) {
                        if (data.success) {
                            appendBubble('✅ Produit mis à jour', 'system');
                        } else {
                            alert(data.message || 'Erreur lors de la mise à jour');
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion lors de la mise à jour');
                    }
                });
            };

            window.sunnyDeleteProduct = function(productId) {
                if (!confirm('Êtes-vous sûr de vouloir supprimer ce produit ?')) {
                    return;
                }

                $.ajax({
                    url: PRODUCTS_URL + '/' + currentPoolId + '/products/' + productId,
                    method: 'DELETE',
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.success) {
                            loadProducts();
                            appendBubble('🗑️ Produit supprimé', 'system');
                        } else {
                            alert(data.message || 'Erreur lors de la suppression');
                        }
                    },
                    error: function() {
                        alert('Erreur de connexion lors de la suppression');
                    }
                });
            };

            // === Fonctions internes ===
            function buildAnalyse() {
                // Les inputs ont les IDs : ana-ph, ana-chlore, ana-tac, ana-stabilisant, ana-temperature
                const result = {};
                const fields = ['ph', 'chlore', 'tac', 'stabilisant', 'temperature'];
                let hasValue = false;
                fields.forEach(function(f) {
                    const el = document.getElementById('ana-' + f);
                    if (el && el.value !== '') {
                        result[f] = parseFloat(el.value);
                        hasValue = true;
                    }
                });
                return hasValue ? result : {};
            }

            function sendToSunny(message, imageBase64, analyse) {
                isLoading = true;
                document.getElementById('sunny-send').disabled = true;

                const typingElement = appendTyping();

                // ID unique pour suivre cette conversation (utilisé par le polling)
                const conversationId = 'conv-<?php echo get_current_user_id(); ?>-' + currentPoolId + '-' + Date.now();

                // Construire le payload
                // image_base64 : le PHP attend la chaîne BASE64 PURE (sans "data:image/...")
                // Le JS stocke l'image avec le préfixe, on le retire ici si nécessaire
                let imagePure = null;
                if (imageBase64) {
                    const match = imageBase64.match(/^data:image\/[^;]+;base64,(.+)$/);
                    imagePure = match ? match[1] : imageBase64;
                }

                const payload = {
                    message:         message,
                    pool_id:         currentPoolId,
                    image_base64:    imagePure,       // base64 pur ou null
                    analyse:         (Object.keys(analyse).length > 0) ? analyse : null,
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
                            // Réponse synchrone directe (ne devrait pas arriver en mode async)
                            if (data.response && data.response !== 'pending') {
                                removeTyping(typingElement);
                                appendBubble(formatResponse(data.response), 'sunny', true);
                                if (data.alertes && data.alertes.length) appendAlertes(data.alertes);
                                if (data.score_eau !== null && data.score_eau !== undefined) appendScoreEau(data.score_eau);
                                if (data.analyse_extraite) prefillAnalyse(data.analyse_extraite);
                                sunnyRemoveImage();
                                isLoading = false;
                                document.getElementById('sunny-send').disabled = false;
                            } else {
                                // Mode async : polling jusqu'à la réponse du callback n8n
                                pollForResponse(data.conversation_id || conversationId, typingElement, 0);
                            }
                        } else {
                            removeTyping(typingElement);
                            appendBubble('⚠️ ' + (data.message || 'Désolé, une erreur est survenue. Réessayez.'), 'system');
                            isLoading = false;
                            document.getElementById('sunny-send').disabled = false;
                        }
                    },
                    error: function(xhr) {
                        console.error('[Sunny] Erreur HTTP:', xhr.status, xhr.responseText);
                        removeTyping(typingElement);
                        appendBubble('Erreur de connexion (' + xhr.status + '). Vérifiez votre connexion internet.', 'system');
                        isLoading = false;
                        document.getElementById('sunny-send').disabled = false;
                    }
                });
            }

            function pollForResponse(conversationId, typingElement, attempts) {
                const maxAttempts = 60; // 2 minutes max
                const pollInterval = 2000; // 2 secondes

                if (attempts >= maxAttempts) {
                    removeTyping(typingElement);
                    appendBubble('Sunny met plus de temps que prévu... Laissez-lui un instant et vérifiez dans quelques secondes.', 'system');
                    isLoading = false;
                    document.getElementById('sunny-send').disabled = false;
                    return;
                }

                $.ajax({
                    url: '<?php echo esc_url(rest_url("sunny-pool/v1/chat/poll")); ?>?conversation_id=' + encodeURIComponent(conversationId),
                    headers: { 'X-WP-Nonce': NONCE },
                    success: function(data) {
                        if (data.found && data.response) {
                            // Réponse trouvée !
                            removeTyping(typingElement);
                            appendBubble(formatResponse(data.response), 'sunny', true);

                            // Afficher les alertes
                            if (data.alertes && data.alertes.length) appendAlertes(data.alertes);

                            // Afficher le score qualité eau
                            if (data.score_eau !== null && data.score_eau !== undefined) {
                                appendScoreEau(data.score_eau);
                            }

                            // Pré-remplir les champs d'analyse si l'IA a extrait des valeurs
                            if (data.analyse_extraite) {
                                prefillAnalyse(data.analyse_extraite);
                            }

                            sunnyRemoveImage();
                            isLoading = false;
                            document.getElementById('sunny-send').disabled = false;
                        } else {
                            // Pas encore de réponse, continuer le polling
                            setTimeout(function() {
                                pollForResponse(conversationId, typingElement, attempts + 1);
                            }, pollInterval);
                        }
                    },
                    error: function() {
                        // En cas d'erreur, réessayer
                        setTimeout(function() {
                            pollForResponse(conversationId, typingElement, attempts + 1);
                        }, pollInterval);
                    }
                });
            }

            function appendBubble(text, type, isHTML) {
                isHTML = isHTML || false;
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
                    const alert = document.createElement('div');
                    alert.className = 'sunny-alerte ' + (a.urgence || 'faible');
                    alert.textContent = '⚠️ ' + a.msg;
                    div.appendChild(alert);
                });
                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
            }

            // Mise en forme basique de la réponse Sunny (gras, sauts de ligne)
            function formatResponse(text) {
                return text
                    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                    .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                    .replace(/\n/g, '<br>');
            }

            // ── Pré-remplir les champs analyse avec les valeurs extraites de l'image ──
            function prefillAnalyse(analyse) {
                if (!analyse) return;
                const mapping = {
                    ph:          'ana-ph',
                    chlore:      'ana-chlore',
                    tac:         'ana-tac',
                    stabilisant: 'ana-stabilisant',
                    temperature: 'ana-temperature'
                };
                let filled = 0;
                Object.keys(mapping).forEach(function(key) {
                    if (analyse[key] !== null && analyse[key] !== undefined) {
                        const el = document.getElementById(mapping[key]);
                        if (el) { el.value = analyse[key]; filled++; }
                    }
                });
                if (filled > 0) {
                    // Ouvrir le panneau analyse automatiquement
                    const panel = document.getElementById('sunny-analyse-panel');
                    const btn   = document.getElementById('toggle-analyse-btn');
                    if (panel && !analyseOpen) {
                        panel.style.display = 'flex';
                        analyseOpen = true;
                        if (btn) btn.textContent = '📊 Masquer Analyse';
                    }
                    // Notification discrète
                    appendBubble('✅ ' + filled + ' valeur(s) extraite(s) de l\'image et pré-remplies dans le formulaire.', 'system');
                }
            }

            // ── Afficher le score de qualité de l'eau ──────────────────
            function appendScoreEau(score) {
                if (score === null || score === undefined) return;
                const msgs = document.getElementById('sunny-messages');
                const div  = document.createElement('div');
                div.style.cssText = 'align-self:center; margin:4px 0; font-size:0.88em; color:#d4af37;';

                let emoji, label, color;
                if      (score >= 85) { emoji = '🟢'; label = 'Excellente'; color = '#2ecc71'; }
                else if (score >= 65) { emoji = '🟡'; label = 'Correcte';   color = '#f39c12'; }
                else if (score >= 40) { emoji = '🟠'; label = 'À corriger'; color = '#e67e22'; }
                else                  { emoji = '🔴'; label = 'Critique';   color = '#e74c3c'; }

                div.innerHTML = emoji + ' <strong style="color:' + color + '">Qualité eau : ' + label + ' (' + score + '/100)</strong>';
                msgs.appendChild(div);
                msgs.scrollTop = msgs.scrollHeight;
            }
        });

    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}