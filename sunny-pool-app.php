<?php
/**
 * Plugin Name: Sunny Pool App
 * Description: Gestion du dashboard piscine utilisateur avec shortcode formulaire
 * Version: 2.2
 * Author: Fidy
 */

if (!defined('ABSPATH')) exit;

// ========== CRÉATION / MISE À JOUR TABLE BASE DE DONNÉES ==========
// Exécuté à l'activation ET à chaque chargement si la version DB a changé
register_activation_hook(__FILE__, 'sunny_pool_create_db');
add_action('plugins_loaded', function() {
    if (get_option('sunny_pool_db_version') !== '2.3') {
        sunny_pool_create_db();
        update_option('sunny_pool_db_version', '2.3');
    }
});
function sunny_pool_create_db() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    // Table des conversations (threads)
    $table_conversations = $wpdb->prefix . 'sunny_chat_conversations';
    $sql_conversations = "CREATE TABLE $table_conversations (\n        id bigint(20) NOT NULL AUTO_INCREMENT,\n        user_id bigint(20) NOT NULL,\n        pool_id bigint(20) NOT NULL,\n        title varchar(255) DEFAULT 'Nouvelle discussion',\n        status varchar(20) DEFAULT 'active',\n        created_at datetime DEFAULT CURRENT_TIMESTAMP,\n        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n        PRIMARY KEY (id),\n        KEY user_id (user_id),\n        KEY pool_id (pool_id),\n        KEY status (status)\n    ) $charset_collate;";

    // Table des messages (mise à jour - conversation_id devient INT)
    $table_messages = $wpdb->prefix . 'sunny_chat_messages';
    $sql_messages = "CREATE TABLE $table_messages (\n        id bigint(20) NOT NULL AUTO_INCREMENT,\n        user_id bigint(20) NOT NULL,\n        pool_id bigint(20) NOT NULL,\n        conversation_id bigint(20) DEFAULT 0,\n        session_id varchar(100) NOT NULL,\n        message text NOT NULL,\n        response text,\n        analyse_extraite text DEFAULT NULL,\n        score_eau tinyint(3) DEFAULT NULL,\n        alertes_json text DEFAULT NULL,\n        has_image tinyint(1) DEFAULT 0,\n        status varchar(20) DEFAULT 'pending',\n        created_at datetime DEFAULT CURRENT_TIMESTAMP,\n        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n        PRIMARY KEY (id),\n        KEY user_id (user_id),\n        KEY pool_id (pool_id),\n        KEY conversation_id (conversation_id),\n        KEY status (status)\n    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_conversations);
    dbDelta($sql_messages);

    error_log('[Sunny Pool] Tables créées/mises à jour: ' . $table_conversations . ', ' . $table_messages);
}

// Inclure le fichier API REST
require_once plugin_dir_path(__FILE__) . 'sunny-pool-api.php';

// ========== SHORTCODE : Affichage des piscines de l'utilisateur ==========
add_shortcode('user_piscine', 'sunny_pool_display');
function sunny_pool_display() {
    if (!is_user_logged_in()) {
        return '<p>Veuillez vous connecter.</p>';
    }

    $user_id = get_current_user_id();

    $args = [
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'   => 'proprietaire',
                'value' => $user_id,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);

    if (!$query->have_posts()) {
        return '<p>Aucune piscine trouvée. <a href="' . home_url('/ajouter-ma-piscine') . '">Ajoutez votre première piscine</a>.</p>';
    }

    ob_start();
    while ($query->have_posts()) {
        $query->the_post();
        $post_id = get_the_ID();
        $type_raw   = get_field('type_piscine');
        $type       = ['enterree' => 'Enterrée', 'hors_sol' => 'Hors-sol'][$type_raw] ?? ucfirst((string)$type_raw);
        $volume     = get_field('volume');
        $filtration_raw = get_field('type_filtration');
        $filtration = ['sable' => 'Filtre sable', 'cartouche' => 'Cartouche', 'electrolyse' => 'Électrolyse', 'verre' => 'Verre'][$filtration_raw] ?? ucfirst((string)$filtration_raw);
        $photos    = get_field('photos_de_la_piscine');
        ?>
        <div class="pool-card">
            <div class="pool-card-header">
                <h3><?php the_title(); ?></h3>
                <div class="pool-card-icon">🏊</div>
            </div>
            <div class="pool-card-content">
                <div class="pool-info">
                    <p><span class="info-label">Type :</span> <?php echo esc_html($type); ?></p>
                    <p><span class="info-label">Volume :</span> <?php echo esc_html($volume); ?> m³</p>
                    <p><span class="info-label">Filtration :</span> <?php echo esc_html($filtration); ?></p>
                </div>
                <?php if ($photos) : ?>
                    <div class="pool-gallery-preview">
                        <?php 
                        $photo_count = count($photos);
                        $display_photos = array_slice($photos, 0, 3);
                        foreach ($display_photos as $photo) : 
                            if (is_array($photo) && isset($photo['ID'])) {
                                $image_id = $photo['ID'];
                            } elseif (is_numeric($photo)) {
                                $image_id = $photo;
                            } else {
                                continue;
                            }
                            echo wp_get_attachment_image($image_id, 'thumbnail', false, ['class' => 'pool-thumbnail']);
                        endforeach; 
                        if ($photo_count > 3) : ?>
                            <div class="more-photos">+<?php echo $photo_count - 3; ?></div>
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                    <div class="no-photos">
                        <span class="no-photos-icon">📷</span>
                        <p><em>Aucune photo enregistrée</em></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="pool-card-footer">
                <a href="<?php echo get_permalink(); ?>" class="view-details-btn">
                    <span>Voir les détails</span>
                    <span class="arrow">→</span>
                </a>
                <a href="<?php echo home_url('/chat-sunny/?pool_id=' . get_the_ID()); ?>" class="view-details-btn" style="background:rgba(212,175,55,0.15); margin-top:8px;">
                    <span>💬 Parler à Sunny</span>
                    <span class="arrow">→</span>
                </a>
            </div>
        </div>
        <?php
    }
    wp_reset_postdata();
    return ob_get_clean();
}

// ========== FORMULAIRE D'AJOUT DE PISCINE ==========
add_shortcode('sunny_pool_form', 'sunny_pool_form_shortcode');
function sunny_pool_form_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>Veuillez vous <a href="' . wp_login_url(get_permalink()) . '">connecter</a> pour ajouter votre piscine.</p>';
    }

    // Traitement du formulaire
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sunny_pool_nonce'])) {
        $result = sunny_pool_handle_submission();
        if (is_wp_error($result)) {
            echo '<div class="sunny-error">' . esc_html($result->get_error_message()) . '</div>';
        } else {
            echo '<div class="sunny-success">Piscine ajoutée avec succès ! Vous allez être redirigé vers votre liste de piscine.</div>';
            echo '<meta http-equiv="refresh" content="2;url=' . esc_url(home_url('/mes-piscines/')) . '">';
        }
    }

    ob_start();
    ?>
    <form method="post" enctype="multipart/form-data" class="sunny-pool-form">
        <?php wp_nonce_field('sunny_pool_action', 'sunny_pool_nonce'); ?>

        <!-- Section Piscine -->
        <h3>Informations piscine</h3>

        <p>
            <label for="nom_piscine">Nom de la piscine *</label>
            <input type="text" name="nom_piscine" id="nom_piscine" required placeholder="ex: Piscine maison">
        </p>

        <p>
            <label for="type_piscine">Type de piscine *</label>
            <select name="type_piscine" id="type_piscine" required>
                <option value="enterree">Entérrée</option>
                <option value="hors_sol">Hors sol</option>
            </select>
        </p>

        <p>
            <label for="longueur">Longueur (m) *</label>
            <input type="number" step="any" name="longueur" id="longueur" required placeholder="ex: 8">
        </p>

        <p>
            <label for="largeur">Largeur (m) *</label>
            <input type="number" step="any" name="largeur" id="largeur" required placeholder="ex: 4">
        </p>

        <p>
            <label for="profondeur">Profondeur moyenne (m) *</label>
            <input type="number" step="any" name="profondeur" id="profondeur" required placeholder="ex: 1.5">
        </p>

        <p>
            <label for="type_filtration">Type de filtration</label>
            <select name="type_filtration" id="type_filtration">
                <option value="sable">Filtration à sable</option>
                <option value="cartouche">Filtration cartouche</option>
                <option value="electrolyse">Électrolyse</option>
            </select>
        </p>

        <p>
            <label for="type_traitement">Type de traitement *</label>
            <select name="type_traitement" id="type_traitement" required>
                <option value="chlore">Chlore</option>
                <option value="brome">Brome</option>
                <option value="electrolyse">Électrolyse</option>
                <option value="uv">UV</option>
                <option value="oxygene_actif">Oxygène actif</option>
            </select>
        </p>

        <!-- Section Équipements -->
        <h3>Équipements</h3>
        <p>
            <label><input type="checkbox" name="equipements[]" value="PAC"> PAC</label><br>
            <label><input type="checkbox" name="equipements[]" value="robot"> Robot</label><br>
            <label><input type="checkbox" name="equipements[]" value="spots_led"> Spots LED</label><br>
            <label><input type="checkbox" name="equipements[]" value="volet"> Volet</label><br>
            <label><input type="checkbox" name="equipements[]" value="bache"> Bâche</label>
        </p>

        <!-- Section Produits -->
        <h3>Produits d'entretien</h3>
        <div id="products-container">
            <div class="product-row">
                <select name="products[0][categorie]" class="product-categorie" data-index="0" required>
                    <option value="">-- Catégorie --</option>
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
                <input type="text" name="products[0][marque]" placeholder="Marque (ex: HTH)" class="product-marque" required>
                <input type="text" name="products[0][nom]" placeholder="Nom du produit" class="product-name" required>
                <input type="number" step="any" name="products[0][quantite]" placeholder="Quantité" class="product-quantity">
                <select name="products[0][unite]" class="product-unit">
                    <option value="L">Litres (L)</option>
                    <option value="kg">Kilogrammes (kg)</option>
                    <option value="g">Grammes (g)</option>
                    <option value="ml">Millilitres (ml)</option>
                    <option value="comprimes">Comprimés</option>
                    <option value="unites">Unités</option>
                </select>
                <input type="text" name="products[0][commentaire]" placeholder="Commentaire (optionnel)" class="product-commentaire">
                <input type="date" name="products[0][date]" value="<?php echo date('Y-m-d'); ?>" class="product-date">
                <div class="product-photos-upload">
                    <label class="photo-label">
                        📷 Face
                        <input type="file" name="products[0][photo_face]" accept="image/*" capture="environment" class="product-photo-face" onchange="previewProductPhoto(this, 'preview-face-0')">
                        <img id="preview-face-0" class="photo-preview-mini" style="display:none;" alt="">
                    </label>
                    <label class="photo-label">
                        📄 Notice
                        <input type="file" name="products[0][photo_notice]" accept="image/*" capture="environment" class="product-photo-notice" onchange="previewProductPhoto(this, 'preview-notice-0')">
                        <img id="preview-notice-0" class="photo-preview-mini" style="display:none;" alt="">
                    </label>
                </div>
                <button type="button" class="remove-product" style="display:none;">Supprimer</button>
            </div>
        </div>
        <button type="button" id="add-product">+ Ajouter un produit</button>

        <!-- Section Adresse -->
        <h3>Adresse de la piscine</h3>
        <p>
            <label for="adresse">Adresse *</label>
            <input type="text" name="adresse" id="adresse" required placeholder="Numéro et nom de rue">
        </p>
        <p>
            <label for="code_postal">Code postal *</label>
            <input type="text" name="code_postal" id="code_postal" required placeholder="Ex: 75001 ,...">
        </p>
        <p>
            <label for="ville">Ville *</label>
            <input type="text" name="ville" id="ville" required>
        </p>
        <p>
            <label for="pays">Pays *</label>
            <select name="pays" id="pays" required>
                <option value="France">France</option>
                <option value="Belgique">Belgique</option>
                <option value="Suisse">Suisse</option>
                <option value="Canada">Canada</option>
                <option value="Maroc">Maroc</option>
                <option value="Côte d'Ivoire">Côte d'Ivoire</option>
                <option value="Sénégal">Sénégal</option>
                <option value="Tunisie">Tunisie</option>
                <option value="Réunion">Réunion</option>
                <option value="Maurice">Maurice</option>
            </select>
        </p>

        <!-- Section Photos -->
        <h3>Photos de la piscine</h3>
        <p>
            <label for="photos">Photos de la piscine (plusieurs possibles)</label>
            <input type="file" name="photos[]" id="photos" multiple accept="image/*">
        </p>

        <p>
            <input type="submit" id="sunny-submit-btn" value="Ajouter ma piscine">
        </p>
    </form>

    <script>
    // Empêcher la double soumission du formulaire
    document.querySelector('.sunny-pool-form').addEventListener('submit', function() {
        const btn = document.getElementById('sunny-submit-btn');
        if (btn) {
            btn.disabled = true;
            btn.value = 'Enregistrement en cours...';
        }
    });
    </script>

    <script>
    // JavaScript pour gérer l'ajout/suppression dynamique des produits
    document.addEventListener('DOMContentLoaded', function() {
        let productCount = 1;
        const container = document.getElementById('products-container');
        const addButton = document.getElementById('add-product');
        
        // Mise à jour des options de produits selon le traitement
        function updateProductOptions() {
            const traitement = document.getElementById('type_traitement').value;
            let options = [];
            
            // Produits communs à tous les traitements
                const communsOptions = [
                    {value: 'ph_plus',          label: 'pH+ (kg)'},
                    {value: 'ph_moins',         label: 'pH- (L ou kg)'},
                    {value: 'stabilisant',      label: 'Stabilisant (kg)'},
                    {value: 'algicide',         label: 'Algicide (L)'},
                    {value: 'clarifiant',       label: 'Clarifiant (L)'},
                    {value: 'produit_nettoyant',label: 'Produit nettoyant (L)'},
                ];

                switch(traitement) {
                case 'chlore':
                    options = [
                        {value: 'chlore_liquide', label: 'Chlore liquide (L)'},
                        {value: 'chlore_galets',  label: 'Chlore en galets (kg)'},
                        {value: 'chlore_choc',    label: 'Chlore choc (kg)'},
                        ...communsOptions
                    ];
                    break;
                case 'brome':
                    options = [
                        {value: 'brome_galets',  label: 'Brome en galets (kg)'},
                        {value: 'brome_liquide', label: 'Brome liquide (L)'},
                        ...communsOptions
                    ];
                    break;
                case 'electrolyse':
                    options = [
                        {value: 'sel_electrolyseur', label: 'Sel pour électrolyseur (kg)'},
                        {value: 'acide',             label: 'Acide muriatique (L)'},
                        ...communsOptions
                    ];
                    break;
                case 'uv':
                case 'oxygene_actif':
                    options = [
                        {value: 'oxygene_actif_prod', label: 'Oxygène actif (L)'},
                        ...communsOptions
                    ];
                    break;
                default:
                    options = communsOptions;
            }
            
            document.querySelectorAll('.product-name').forEach(select => {
                const currentValue = select.value;
                select.innerHTML = '<option value="">-- Sélectionner un produit --</option>';
                options.forEach(opt => {
                    const option = document.createElement('option');
                    option.value = opt.value;
                    option.textContent = opt.label;
                    if (option.value === currentValue) option.selected = true;
                    select.appendChild(option);
                });
            });
        }
        
        // Preview photo produit
        window.previewProductPhoto = function(input, previewId) {
            const preview = document.getElementById(previewId);
            if (!preview || !input.files || !input.files[0]) return;
            const reader = new FileReader();
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        };

        // Ajouter une ligne produit
        function addProductRow() {
            const newRow = document.createElement('div');
            newRow.className = 'product-row';
            newRow.innerHTML = `
                <select name="products[${productCount}][categorie]" class="product-categorie" data-index="${productCount}" required>
                    <option value="">-- Catégorie --</option>
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
                <input type="text" name="products[${productCount}][marque]" placeholder="Marque (ex: HTH)" class="product-marque" required>
                <input type="text" name="products[${productCount}][nom]" placeholder="Nom du produit" class="product-name" required>
                <input type="number" step="any" name="products[${productCount}][quantite]" placeholder="Quantité" class="product-quantity">
                <select name="products[${productCount}][unite]" class="product-unit">
                    <option value="L">Litres (L)</option>
                    <option value="kg">Kilogrammes (kg)</option>
                    <option value="g">Grammes (g)</option>
                    <option value="ml">Millilitres (ml)</option>
                    <option value="comprimes">Comprimés</option>
                    <option value="unites">Unités</option>
                </select>
                <input type="text" name="products[${productCount}][commentaire]" placeholder="Commentaire (optionnel)" class="product-commentaire">
                <input type="date" name="products[${productCount}][date]" value="${new Date().toISOString().split('T')[0]}" class="product-date">
                <div class="product-photos-upload">
                    <label class="photo-label">
                        📷 Face
                        <input type="file" name="products[${productCount}][photo_face]" accept="image/*" capture="environment" class="product-photo-face" onchange="previewProductPhoto(this, 'preview-face-${productCount}')">
                        <img id="preview-face-${productCount}" class="photo-preview-mini" style="display:none;" alt="">
                    </label>
                    <label class="photo-label">
                        📄 Notice
                        <input type="file" name="products[${productCount}][photo_notice]" accept="image/*" capture="environment" class="product-photo-notice" onchange="previewProductPhoto(this, 'preview-notice-${productCount}')">
                        <img id="preview-notice-${productCount}" class="photo-preview-mini" style="display:none;" alt="">
                    </label>
                </div>
                <button type="button" class="remove-product">Supprimer</button>
            `;
            container.appendChild(newRow);
            document.querySelectorAll('.remove-product').forEach((btn, idx) => {
                btn.style.display = idx === 0 ? 'none' : 'inline-block';
            });
            productCount++;
        }
        
        // Supprimer une ligne produit
        function removeProductRow(button) {
            button.closest('.product-row').remove();
            document.querySelectorAll('.product-row').forEach((row, idx) => {
                row.querySelector('.product-categorie').setAttribute('name', `products[${idx}][categorie]`);
                row.querySelector('.product-marque').setAttribute('name', `products[${idx}][marque]`);
                row.querySelector('.product-name').setAttribute('name', `products[${idx}][nom]`);
                row.querySelector('.product-quantity').setAttribute('name', `products[${idx}][quantite]`);
                row.querySelector('.product-unit').setAttribute('name', `products[${idx}][unite]`);
                row.querySelector('.product-commentaire').setAttribute('name', `products[${idx}][commentaire]`);
                row.querySelector('.product-date').setAttribute('name', `products[${idx}][date]`);
                // Mettre à jour les preview IDs
                const faceInput = row.querySelector('.product-photo-face');
                const noticeInput = row.querySelector('.product-photo-notice');
                if (faceInput) faceInput.setAttribute('onchange', `previewProductPhoto(this, 'preview-face-${idx}')`);
                if (noticeInput) noticeInput.setAttribute('onchange', `previewProductPhoto(this, 'preview-notice-${idx}')`);
                const faceImg = row.querySelector('[id^="preview-face-"]');
                const noticeImg = row.querySelector('[id^="preview-notice-"]');
                if (faceImg) faceImg.id = `preview-face-${idx}`;
                if (noticeImg) noticeImg.id = `preview-notice-${idx}`;
            });
            productCount = document.querySelectorAll('.product-row').length;
            document.querySelectorAll('.remove-product').forEach((btn, idx) => {
                btn.style.display = idx === 0 ? 'none' : 'inline-block';
            });
        }
        
        addButton.addEventListener('click', addProductRow);
        container.addEventListener('click', function(e) {
            if (e.target.classList.contains('remove-product')) {
                removeProductRow(e.target);
            }
        });
        document.getElementById('type_traitement').addEventListener('change', updateProductOptions);
        updateProductOptions();

        // ── Calcul de volume automatique ─────────────────────────────
        function calcVolume() {
            const l = parseFloat(document.getElementById('longueur').value) || 0;
            const w = parseFloat(document.getElementById('largeur').value)  || 0;
            const d = parseFloat(document.getElementById('profondeur').value) || 0;
            const vol = l * w * d;
            let display = document.getElementById('volume-display');
            if (!display) {
                display = document.createElement('p');
                display.id = 'volume-display';
                display.style.cssText = 'color:#ffd700; font-weight:bold; font-size:1.1em; margin-top:4px;';
                document.getElementById('profondeur').closest('p').after(display);
            }
            display.textContent = vol > 0 ? '📐 Volume estimé : ' + vol.toFixed(1) + ' m³' : '';
        }
        ['longueur', 'largeur', 'profondeur'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', calcVolume);
        });
    });
    </script>

    <style>
    /* Style général du formulaire */
    .sunny-pool-form {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        border: 2px solid #d4af37;
        border-radius: 15px;
        padding: 30px;
        color: #ffffff;
        box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .sunny-pool-form h3 {
        color: #d4af37;
        font-size: 1.4em;
        margin-bottom: 20px;
        text-align: center;
        text-transform: uppercase;
        letter-spacing: 1px;
        border-bottom: 2px solid #d4af37;
        padding-bottom: 10px;
    }

    .sunny-pool-form label {
        color: #d4af37;
        font-weight: 600;
        display: block;
        margin-bottom: 5px;
    }

    .sunny-pool-form input[type="text"],
    .sunny-pool-form input[type="number"],
    .sunny-pool-form input[type="date"],
    .sunny-pool-form input[type="file"],
    .sunny-pool-form select {
        background: #2d2d2d;
        border: 1px solid #d4af37;
        color: #ffffff;
        padding: 12px;
        border-radius: 8px;
        width: 100%;
        transition: all 0.3s ease;
        box-sizing: border-box;
    }

    .sunny-pool-form input:focus,
    .sunny-pool-form select:focus {
        outline: none;
        border-color: #ffd700;
        box-shadow: 0 0 10px rgba(255, 215, 0, 0.3);
        background: #333333;
    }

    .sunny-pool-form input[type="submit"] {
        background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #d4af37 100%);
        color: #1a1a1a;
        border: none;
        padding: 15px 30px;
        font-size: 1.1em;
        font-weight: bold;
        border-radius: 10px;
        cursor: pointer;
        transition: all 0.3s ease;
        text-transform: uppercase;
        letter-spacing: 1px;
        width: 100%;
        margin-top: 20px;
    }

    .sunny-pool-form input[type="submit"]:hover {
        background: linear-gradient(135deg, #ffd700 0%, #d4af37 50%, #ffd700 100%);
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(255, 215, 0, 0.4);
    }

    .sunny-pool-form p {
        margin-bottom: 20px;
    }

    /* Style pour les checkboxes */
    .sunny-pool-form input[type="checkbox"] {
        margin-right: 10px;
        accent-color: #d4af37;
    }

    .sunny-pool-form .checkbox-group label {
        display: inline-block;
        margin-right: 15px;
        margin-bottom: 10px;
    }

    /* Messages de succès et d'erreur */
    .sunny-success {
        background: linear-gradient(135deg, #1a3d1a 0%, #2d5a2d 100%);
        border: 2px solid #d4af37;
        color: #ffffff;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }

    .sunny-error {
        background: linear-gradient(135deg, #3d1a1a 0%, #5a2d2d 100%);
        border: 2px solid #d4af37;
        color: #ffffff;
        padding: 15px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
        font-weight: bold;
    }

    /* Style pour les produits */
    .product-row {
        margin-bottom: 15px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        background: rgba(212, 175, 55, 0.1);
        padding: 15px;
        border-radius: 10px;
        border: 1px solid rgba(212, 175, 55, 0.3);
    }
    
    .product-row select, .product-row input {
        padding: 10px;
        background: #2d2d2d;
        border: 1px solid #d4af37;
        color: #ffffff;
        border-radius: 6px;
    }
    
    .product-categorie { width: 150px; }
    .product-marque { width: 140px; }
    .product-name { width: 200px; }
    .product-quantity { width: 90px; }
    .product-unit { width: 110px; }
    .product-commentaire { width: 180px; }
    .product-date { width: 120px; }

    .product-photos-upload {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .photo-label {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
        font-size: 0.85em;
        color: #d4af37;
        cursor: pointer;
    }

    .photo-label input[type="file"] {
        display: none;
    }

    .photo-preview-mini {
        width: 40px;
        height: 40px;
        object-fit: cover;
        border-radius: 4px;
        border: 1px solid #d4af37;
    }
    
    .remove-product {
        background: linear-gradient(135deg, #8b0000 0%, #dc143c 100%);
        color: white;
        border: 1px solid #d4af37;
        padding: 8px 15px;
        cursor: pointer;
        border-radius: 6px;
        font-weight: bold;
        transition: all 0.3s ease;
    }
    
    .remove-product:hover {
        background: linear-gradient(135deg, #dc143c 0%, #ff1744 100%);
        box-shadow: 0 3px 10px rgba(220, 20, 60, 0.3);
    }
    
    #add-product {
        margin: 15px 0 25px;
        padding: 12px 20px;
        background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #d4af37 100%);
        color: #1a1a1a;
        border: none;
        cursor: pointer;
        border-radius: 8px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        width: auto;
    }
    
    #add-product:hover {
        background: linear-gradient(135deg, #ffd700 0%, #d4af37 50%, #ffd700 100%);
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
    }

    /* Style pour les cartes de piscines */
    .pool-card {
        background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        border: 2px solid #d4af37;
        border-radius: 15px;
        padding: 0;
        margin-bottom: 20px;
        color: #ffffff;
        box-shadow: 0 8px 25px rgba(212, 175, 55, 0.2);
        transition: all 0.3s ease;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }

    .pool-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(212, 175, 55, 0.3);
        border-color: #ffd700;
    }

    .pool-card-header {
        background: linear-gradient(135deg, rgba(212, 175, 55, 0.2) 0%, rgba(255, 215, 0, 0.1) 100%);
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid rgba(212, 175, 55, 0.3);
    }

    .pool-card-header h3 {
        color: #d4af37;
        margin: 0;
        font-size: 1.3em;
        text-transform: uppercase;
        letter-spacing: 1px;
    }

    .pool-card-icon {
        font-size: 2em;
        opacity: 0.8;
    }

    .pool-card-content {
        padding: 20px;
        flex: 1;
    }

    .pool-info {
        margin-bottom: 20px;
    }

    .pool-info p {
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 5px 0;
        border-bottom: 1px solid rgba(212, 175, 55, 0.1);
    }

    .info-label {
        color: #d4af37;
        font-weight: 600;
    }

    .pool-gallery-preview {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-top: 15px;
    }

    .pool-thumbnail {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 8px;
        border: 2px solid #d4af37;
        transition: all 0.3s ease;
    }

    .pool-thumbnail:hover {
        border-color: #ffd700;
        transform: scale(1.1);
        box-shadow: 0 3px 10px rgba(255, 215, 0, 0.3);
    }

    .more-photos {
        background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
        color: #1a1a1a;
        width: 60px;
        height: 60px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.1em;
        border: 2px solid #d4af37;
    }

    .no-photos {
        text-align: center;
        padding: 20px;
        background: rgba(212, 175, 55, 0.05);
        border-radius: 10px;
        border: 1px solid rgba(212, 175, 55, 0.2);
    }

    .no-photos-icon {
        font-size: 2em;
        display: block;
        margin-bottom: 10px;
        opacity: 0.6;
    }

    .pool-card-footer {

        .view-details-btn {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #d4af37 100%);
            color: #000000;
            padding: 14px 22px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-size: 0.95em;
            transition: all 0.3s ease;
            width: 100%;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .view-details-btn:hover {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 50%, #ffd700 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
            color: #000000;
            text-shadow: 0 1px 3px rgba(255, 255, 255, 0.5);
        }

        .arrow {
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }
    }

    .view-details-btn:hover .arrow {
        transform: translateX(5px);
    }

    .pool-gallery {
        margin: 15px 0;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
    }

    .pool-gallery img {
        border-radius: 8px;
        border: 2px solid #d4af37;
        transition: all 0.3s ease;
    }

    .pool-gallery img:hover {
        border-color: #ffd700;
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(255, 215, 0, 0.3);
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Convertit une adresse en coordonnées GPS via OpenStreetMap Nominatim
 *
 * @param string $adresse Adresse complète
 * @return array|false Tableau ['lat' => float, 'lon' => float] ou false en cas d'échec
 */
function sunny_geocode_address($adresse) {
    // Nettoyer et encoder l'adresse
    $adresse_encoded = urlencode($adresse);
    
    // Appel à l'API Nominatim (OpenStreetMap)
    $url = "https://nominatim.openstreetmap.org/search?format=json&q={$adresse_encoded}&limit=1";
    
    // Important : Nominatim demande un User-Agent identifiant votre application
    $args = [
        'headers' => [
            'User-Agent' => 'SunnyPoolApp/1.0 (contact@sunny.trouvezpourmoi.com)'
        ],
        'timeout' => 10
    ];
    
    $response = wp_remote_get($url, $args);
    
    if (is_wp_error($response)) {
        error_log('Erreur géocodage : ' . $response->get_error_message());
        return false;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data) || !isset($data[0]['lat'])) {
        error_log('Géocodage : aucune coordonnée trouvée pour l\'adresse : ' . $adresse);
        return false;
    }
    
    return [
        'lat' => floatval($data[0]['lat']),
        'lon' => floatval($data[0]['lon'])
    ];
}

/**
 * Récupère la météo actuelle pour une piscine via Open-Meteo
 *
 * @param float $lat Latitude
 * @param float $lon Longitude
 * @return string|false Description de la météo ou false en cas d'erreur
 */
function sunny_get_weather($lat, $lon) {
    $url = add_query_arg([
        'latitude'     => $lat,
        'longitude'    => $lon,
        'current'      => 'temperature_2m,relative_humidity_2m,rain,weather_code',
        'timezone'     => 'auto',
        'forecast_days' => 1,
    ], 'https://api.open-meteo.com/v1/forecast');
    
    $response = wp_remote_get($url, ['timeout' => 10]);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $data = json_decode(wp_remote_retrieve_body($response), true);
    
    if (empty($data['current'])) {
        return false;
    }
    
    $temperature = $data['current']['temperature_2m'];
    $unite_temp  = $data['current_units']['temperature_2m'];
    $humidite    = $data['current']['relative_humidity_2m'];
    $pluie       = $data['current']['rain'] ?? 0;
    
    // Code météo simplifié (optionnel)
    $weather_code = $data['current']['weather_code'] ?? 0;
    $weather_icon = '';
    if ($weather_code >= 61 && $weather_code <= 67) $weather_icon = '🌧️';
    elseif ($weather_code >= 71 && $weather_code <= 77) $weather_icon = '❄️';
    elseif ($weather_code >= 80 && $weather_code <= 82) $weather_icon = '☔';
    elseif ($weather_code >= 95 && $weather_code <= 99) $weather_icon = '⛈️';
    elseif ($weather_code <= 3) $weather_icon = '☀️';
    else $weather_icon = '🌤️';
    
    return sprintf(
        '%s %s%s, 💧 %s%%, 🌧️ %s mm',
        $weather_icon,
        $temperature,
        $unite_temp,
        $humidite,
        $pluie
    );
}
// ========== TRAITEMENT DE LA SOUMISSION ==========
function sunny_pool_handle_submission() {
    if (!wp_verify_nonce($_POST['sunny_pool_nonce'], 'sunny_pool_action')) {
        return new WP_Error('invalid_nonce', 'Action non valide.');
    }

    $nom_piscine    = sanitize_text_field($_POST['nom_piscine'] ?? '');
    $type_piscine   = sanitize_text_field($_POST['type_piscine']);
    $longueur       = floatval($_POST['longueur']);
    $largeur        = floatval($_POST['largeur']);
    $profondeur     = floatval($_POST['profondeur']);
    $type_filtration = sanitize_text_field($_POST['type_filtration']);
    $type_traitement = sanitize_text_field($_POST['type_traitement']);
    $equipements    = isset($_POST['equipements']) ? array_map('sanitize_text_field', $_POST['equipements']) : [];

    if (empty($type_piscine) || empty($longueur) || empty($largeur) || empty($profondeur)) {
        return new WP_Error('missing_fields', 'Veuillez remplir tous les champs obligatoires.');
    }

    // Création du post
    $post_title = !empty($nom_piscine) ? $nom_piscine : 'Piscine ' . (['enterree' => 'enterrée', 'hors_sol' => 'hors-sol'][$type_piscine] ?? $type_piscine);
    $post_id = wp_insert_post([
        'post_title'   => $post_title,
        'post_type'    => 'piscine',
        'post_status'  => 'publish',
        'post_author'  => get_current_user_id(),
    ]);

    if (is_wp_error($post_id)) {
        return new WP_Error('post_creation', 'Erreur lors de la création de la piscine.');
    }

    // Gestion des photos
    $photo_ids = [];
    if (!empty($_FILES['photos'])) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $files = $_FILES['photos'];
        $file_count = count($files['name']);
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name'     => $files['name'][$i],
                    'type'     => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error'    => $files['error'][$i],
                    'size'     => $files['size'][$i],
                ];
                $attachment_id = media_handle_sideload($file, $post_id);
                if (!is_wp_error($attachment_id)) {
                    $photo_ids[] = $attachment_id;
                } else {
                    error_log('Erreur upload photo : ' . $attachment_id->get_error_message());
                }
            }
        }
    }

    // Mise à jour ACF
    update_field('type_piscine',       $type_piscine,       $post_id);
    update_field('longueur',           $longueur,           $post_id);
    update_field('largeur',            $largeur,            $post_id);
    update_field('profondeur',         $profondeur,         $post_id);
    update_field('type_filtration',    $type_filtration,    $post_id);
    update_field('type_traitement',    $type_traitement,    $post_id);
    update_field('equipements',        $equipements,        $post_id);
    update_field('photos_de_la_piscine', $photo_ids,        $post_id);

    // Calcul volume
    $volume = $longueur * $largeur * $profondeur;
    update_field('volume', $volume, $post_id);

    // Lier l'utilisateur
    $user_id = get_current_user_id();
    update_field('proprietaire', $user_id, $post_id);

    // Récupération des champs adresse
    $adresse      = sanitize_text_field($_POST['adresse']);
    $code_postal  = sanitize_text_field($_POST['code_postal']);
    $ville        = sanitize_text_field($_POST['ville']);
    $pays         = sanitize_text_field($_POST['pays']);

    // Construction de l'adresse complète pour le géocodage
    $adresse_complete = $adresse . ', ' . $code_postal . ' ' . $ville . ', ' . $pays;

    // Géocodage
    $coordinates = sunny_geocode_address($adresse_complete);
    if ($coordinates) {
        update_field('latitude', $coordinates['lat'], $post_id);
        update_field('longitude', $coordinates['lon'], $post_id);
    } else {
        // Optionnel : enregistrer un message d'erreur dans les logs
        error_log('Impossible de géocoder l\'adresse pour la piscine ID ' . $post_id);
    }

    // Sauvegarde de l'adresse textuelle (pour affichage)
    update_field('adresse_textuelle', $adresse_complete, $post_id);

    // Gestion des produits
    $produits = [];
    if (isset($_POST['products']) && is_array($_POST['products'])) {
        foreach ($_POST['products'] as $index => $product) {
            if (!empty($product['nom']) && !empty($product['categorie']) && !empty($product['marque'])) {
                $produit_data = [
                    'id'           => uniqid('prod_'),
                    'categorie'    => sanitize_text_field($product['categorie']),
                    'marque'       => sanitize_text_field($product['marque']),
                    'nom_produit'  => sanitize_text_field($product['nom']),
                    'quantite'     => floatval($product['quantite']) ?: 0,
                    'unite'        => sanitize_text_field($product['unite']),
                    'commentaire'  => sanitize_text_field($product['commentaire'] ?? ''),
                    'date_ajout'   => sanitize_text_field($product['date']),
                    'photo_face'   => '',
                    'photo_notice' => '',
                ];

                // Gestion des photos des produits
                if (!empty($_FILES['products']['tmp_name'][$index]['photo_face'])) {
                    $face_file = [
                        'name'     => $_FILES['products']['name'][$index]['photo_face'],
                        'type'     => $_FILES['products']['type'][$index]['photo_face'],
                        'tmp_name' => $_FILES['products']['tmp_name'][$index]['photo_face'],
                        'error'    => $_FILES['products']['error'][$index]['photo_face'],
                        'size'     => $_FILES['products']['size'][$index]['photo_face'],
                    ];
                    if ($face_file['error'] === UPLOAD_ERR_OK) {
                        $face_id = media_handle_sideload($face_file, $post_id);
                        if (!is_wp_error($face_id)) {
                            $produit_data['photo_face'] = wp_get_attachment_url($face_id);
                        }
                    }
                }

                if (!empty($_FILES['products']['tmp_name'][$index]['photo_notice'])) {
                    $notice_file = [
                        'name'     => $_FILES['products']['name'][$index]['photo_notice'],
                        'type'     => $_FILES['products']['type'][$index]['photo_notice'],
                        'tmp_name' => $_FILES['products']['tmp_name'][$index]['photo_notice'],
                        'error'    => $_FILES['products']['error'][$index]['photo_notice'],
                        'size'     => $_FILES['products']['size'][$index]['photo_notice'],
                    ];
                    if ($notice_file['error'] === UPLOAD_ERR_OK) {
                        $notice_id = media_handle_sideload($notice_file, $post_id);
                        if (!is_wp_error($notice_id)) {
                            $produit_data['photo_notice'] = wp_get_attachment_url($notice_id);
                        }
                    }
                }

                $produits[] = $produit_data;
            }
        }
    }
    if (!empty($produits)) {
        update_post_meta($post_id, '_sunny_produits', wp_json_encode($produits));
        update_field('produits', $produits, $post_id);
    }

    return true;
}

// ========== SHORTCODE CHAT SUNNY ==========
require_once plugin_dir_path(__FILE__) . 'sunny-chat-shortcode.php';

// ========== TEMPLATE PERSONNALISÉ POUR LE CPT ==========
add_filter('single_template', function($template) {
    global $post;
    if ($post->post_type === 'piscine') {
        $plugin_template = plugin_dir_path(__FILE__) . 'single-piscine.php';
        if (file_exists($plugin_template)) {
            return $plugin_template;
        }
    }
    return $template;
});

// Étendre la durée du token JWT à 7 jours
add_filter('mo_jwt_auth_token_expire', function($expire, $issued_at) {
    return $issued_at + (7 * DAY_IN_SECONDS); // 7 jours
}, 10, 2);