<?php
/**
 * Template personnalisé pour l'affichage d'une piscine
 * Ce fichier doit être placé dans le même dossier que le plugin
 */

// Récupération de l'en-tête du site (si vous voulez garder le thème)
get_header();
?>

<div class="container pool-detail" style="max-width: 1200px; margin: 0 auto; padding: 20px;">
    <style>
        /* Style général pour la page détail */
        .pool-detail {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
            border: 2px solid #d4af37;
            border-radius: 20px;
            margin: 20px auto;
            padding: 40px;
            box-shadow: 0 15px 40px rgba(212, 175, 55, 0.3);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .pool-single {
            color: #ffffff;
        }

        .pool-single h1 {
            color: #d4af37;
            text-align: center;
            font-size: 2.5em;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }

        .pool-single h2 {
            color: #d4af37;
            font-size: 1.8em;
            margin-top: 40px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
            border-bottom: 2px solid #d4af37;
            padding-bottom: 10px;
        }

        .pool-single p {
            margin-bottom: 15px;
            line-height: 1.6;
            font-size: 1.1em;
        }

        .pool-single strong {
            color: #d4af37;
            font-weight: 600;
        }

        .pool-single ul {
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid rgba(212, 175, 55, 0.3);
            border-radius: 15px;
            padding: 25px;
            margin: 20px 0;
        }

        .pool-single li {
            margin-bottom: 12px;
            padding: 8px 0;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            font-size: 1.1em;
        }

        .pool-single li:last-child {
            border-bottom: none;
        }

        .pool-single li strong {
            color: #ffd700;
            font-weight: bold;
        }

        /* Style pour la météo */
        .weather-info {
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.2) 0%, rgba(255, 215, 0, 0.1) 100%);
            border: 2px solid #d4af37;
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
            font-size: 1.2em;
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.2);
        }

        .weather-info strong {
            color: #ffd700;
            font-size: 1.3em;
            display: block;
            margin-bottom: 10px;
        }

        /* Style pour la galerie de photos */
        .pool-gallery {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .pool-gallery img {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            border: 3px solid #d4af37;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .pool-gallery img:hover {
            border-color: #ffd700;
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(255, 215, 0, 0.4);
        }

        /* Style pour le tableau des produits */
        table {
            background: rgba(212, 175, 55, 0.05);
            border: 2px solid #d4af37;
            border-radius: 15px;
            overflow: hidden;
            margin: 25px 0;
            box-shadow: 0 5px 20px rgba(212, 175, 55, 0.2);
        }

        thead th {
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 100%);
            color: #1a1a1a;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 15px;
            text-align: left;
        }

        tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.2);
            color: #ffffff;
            font-size: 1.05em;
        }

        tbody tr:nth-child(even) {
            background: rgba(212, 175, 55, 0.1);
        }

        tbody tr:hover {
            background: rgba(212, 175, 55, 0.2);
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        /* Bouton de retour */
        .pool-single a {
            display: inline-block;
            background: linear-gradient(135deg, #d4af37 0%, #ffd700 50%, #d4af37 100%);
            color: #000000;
            padding: 16px 32px;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 1.2px;
            font-size: 1.05em;
            transition: all 0.3s ease;
            margin-top: 30px;
            text-align: center;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.3);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        .pool-single a:hover {
            background: linear-gradient(135deg, #ffd700 0%, #d4af37 50%, #ffd700 100%);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 215, 0, 0.4);
            color: #000000;
            text-shadow: 0 1px 3px rgba(255, 255, 255, 0.5);
        }

        /* Messages d'information */
        .pool-single em {
            color: #d4af37;
            font-style: italic;
            font-size: 1.1em;
        }

        /* Styles pour la gestion des produits */
        .product-actions {
            display: flex;
            gap: 8px;
        }

        .btn-edit, .btn-delete, .btn-save, .btn-cancel {
            padding: 6px 12px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.85em;
            transition: all 0.2s ease;
        }

        .btn-edit {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #1a1a1a;
        }

        .btn-edit:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4);
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: #fff;
        }

        .btn-delete:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.4);
        }

        .btn-save {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: #fff;
        }

        .btn-save:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: #fff;
        }

        .btn-cancel:hover {
            transform: scale(1.05);
            box-shadow: 0 2px 8px rgba(149, 165, 166, 0.4);
        }

        .product-form {
            background: rgba(212, 175, 55, 0.1);
            border: 2px solid #d4af37;
            border-radius: 15px;
            padding: 25px;
            margin: 25px 0;
        }

        .product-form h3 {
            color: #d4af37;
            margin: 0 0 20px 0;
            font-size: 1.3em;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-row-3 {
            display: grid;
            grid-template-columns: 1fr 120px 100px auto;
            gap: 15px;
            align-items: end;
            margin-bottom: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            color: #d4af37;
            font-size: 0.9em;
            margin-bottom: 5px;
        }

        .form-group select,
        .form-group input,
        .form-group textarea {
            padding: 10px;
            background: #2d2d2d;
            border: 1px solid #d4af37;
            color: #fff;
            border-radius: 6px;
            font-size: 1em;
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-group select:focus,
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #ffd700;
            box-shadow: 0 0 8px rgba(255, 215, 0, 0.3);
        }

        .photo-upload-group {
            background: rgba(212, 175, 55, 0.05);
            border: 1px dashed #d4af37;
            border-radius: 8px;
            padding: 15px;
        }

        .photo-upload-group label {
            color: #d4af37;
            font-size: 0.9em;
            margin-bottom: 8px;
            display: block;
        }

        .photo-upload-group input[type="file"] {
            color: #fff;
            font-size: 0.9em;
        }

        .photo-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 6px;
            margin-top: 8px;
            border: 1px solid #d4af37;
        }

        .btn-add-product {
            background: linear-gradient(135deg, #d4af37, #ffd700);
            color: #1a1a1a;
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-weight: bold;
            cursor: pointer;
            font-size: 1em;
            transition: all 0.2s ease;
        }

        .btn-add-product:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
        }

        .edit-form {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .edit-form input, .edit-form select {
            padding: 6px 10px;
            background: #2d2d2d;
            border: 1px solid #d4af37;
            color: #fff;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .edit-form input {
            width: 80px;
        }

        .message-feedback {
            padding: 12px 20px;
            border-radius: 8px;
            margin: 15px 0;
            font-weight: 500;
        }

        .message-success {
            background: rgba(46, 204, 113, 0.2);
            border: 1px solid #2ecc71;
            color: #2ecc71;
        }

        .message-error {
            background: rgba(231, 76, 60, 0.2);
            border: 1px solid #e74c3c;
            color: #e74c3c;
        }

        .product-photos {
            display: flex;
            gap: 10px;
        }

        .product-photos img {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #d4af37;
            cursor: pointer;
        }

        .product-photos img:hover {
            transform: scale(1.1);
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4);
        }

        /* Modal pour afficher les photos */
        .photo-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
        }

        .photo-modal-content {
            position: relative;
            margin: 5% auto;
            max-width: 80%;
            text-align: center;
        }

        .photo-modal-content img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 10px;
            border: 3px solid #d4af37;
        }

        .photo-modal-close {
            position: absolute;
            top: -40px;
            right: 0;
            color: #d4af37;
            font-size: 35px;
            font-weight: bold;
            cursor: pointer;
        }

        .photo-modal-close:hover {
            color: #ffd700;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .pool-detail {
                padding: 20px;
                margin: 10px;
            }

            .pool-single h1 {
                font-size: 2em;
            }

            .pool-single h2 {
                font-size: 1.5em;
            }

            .pool-gallery {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }

            .pool-gallery img {
                height: 180px;
            }

            table {
                font-size: 0.9em;
            }

            thead th, tbody td {
                padding: 10px;
            }
        }
    </style>
    <?php while (have_posts()) : the_post(); 
        $post_id = get_the_ID();
        $name        = get_field('name_piscine');
        $type        = get_field('type_piscine');
        $longueur    = get_field('longueur');
        $largeur     = get_field('largeur');
        $profondeur  = get_field('profondeur');
        $volume      = get_field('volume');
        $filtration  = get_field('type_filtration');
        $traitement  = get_field('type_traitement');
        $equipements = get_field('equipements');
        $photos      = get_field('photos_de_la_piscine');
    ?>
        <article class="pool-single">
            <h1><?php the_title(); ?></h1>
            <p><strong>Date d'ajout :</strong> <?php echo get_the_date(); ?></p>
            
            <h2>Caractéristiques techniques</h2>
            <ul style="margin-bottom: 20px;">
                <li><strong>Type :</strong> <?php echo esc_html($type); ?></li>
                <li><strong>Dimensions :</strong> <?php echo esc_html($longueur); ?> m x <?php echo esc_html($largeur); ?> m x <?php echo esc_html($profondeur); ?> m</li>
                <li><strong>Volume :</strong> <?php echo esc_html($volume); ?> m³</li>
                <li><strong>Filtration :</strong> <?php echo esc_html($filtration); ?></li>
                <li><strong>Traitement :</strong> <?php echo esc_html($traitement); ?></li>
                <?php if (!empty($equipements)) : ?>
                    <li><strong>Équipements :</strong> <?php echo implode(', ', $equipements); ?></li>
                <?php endif; ?>
            </ul>
            
            <?php
            // Affichage de la météo
            $lat = get_field('latitude', $post_id);
            $lon = get_field('longitude', $post_id);
            if ($lat && $lon) {
                $weather = sunny_get_weather($lat, $lon);
                if ($weather) {
                    echo '<div class="weather-info"><strong>🌡️ Météo actuelle :</strong> ' . esc_html($weather) . '</div>';
                } else {
                    echo '<div class="weather-info"><em>Météo non disponible actuellement.</em></div>';
                }
            } else {
                echo '<div class="weather-info"><em>Localisation non renseignée.</em></div>';
            }
            ?>
            

            <?php if ($photos) : ?>
                <h2>Photos de la piscine</h2>
                <div class="pool-gallery">
                    <?php foreach ($photos as $photo) : 
                        // Gère les deux formats de retour possibles (ID ou objet)
                        if (is_array($photo) && isset($photo['ID'])) {
                            $image_id = $photo['ID'];
                        } elseif (is_numeric($photo)) {
                            $image_id = $photo;
                        } else {
                            continue;
                        }
                        echo wp_get_attachment_image($image_id, 'medium', false, ['style' => 'width:100%; height:auto; border-radius:8px;']);
                    endforeach; ?>
                </div>
            <?php else : ?>
                <p><em>Aucune photo enregistrée pour cette piscine.</em></p>
            <?php endif; ?>
            <?php
                // Lire les produits depuis la meta JSON (source fiable, bypass ACF)
                $produits_json = get_post_meta($post_id, '_sunny_produits', true);
                $produits = $produits_json ? json_decode($produits_json, true) : [];

                // Fallback ACF si pas encore migré
                if (empty($produits)) {
                    $produits_acf = get_field('produits', $post_id);
                    if (!empty($produits_acf) && is_array($produits_acf)) {
                        $produits = $produits_acf;
                    }
                }

                $categories = [
                    'chlore_choc'          => 'Chlore choc',
                    'chlore_lent'          => 'Chlore lent',
                    'ph_plus'              => 'pH +',
                    'ph_moins'             => 'pH -',
                    'anti_algues'          => 'Anti-algues',
                    'clarifiant'           => 'Clarifiant',
                    'floculant'            => 'Floculant',
                    'sequestrant_calcaire' => 'Séquestrant calcaire',
                    'sequestrant_metaux'   => 'Séquestrant métaux',
                ];
            ?>

            <h2>Mes produits d'entretien</h2>

            <!-- Zone feedback -->
            <div id="product-message" style="display:none;"></div>

            <!-- Tableau des produits existants -->
            <?php if (!empty($produits)) : ?>
            <table id="products-table">
                <thead>
                    <tr>
                        <th>Catégorie</th>
                        <th>Marque</th>
                        <th>Nom</th>
                        <th>Qté</th>
                        <th>Unité</th>
                        <th>Photos</th>
                        <th>Date ajout</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($produits as $produit) :
                    $pid   = $produit['id']         ?? '';
                    $cat   = $produit['categorie']   ?? '';
                    $marq  = $produit['marque']      ?? '';
                    $nom   = json_decode('"' . ($produit['nom_produit'] ?? ($produit['nom'] ?? '')) . '"') ?? ($produit['nom_produit'] ?? '');
                    $qty   = $produit['quantite']    ?? 0;
                    $unit  = $produit['unite']       ?? '';
                    $pface = $produit['photo_face']  ?? '';
                    $pnot  = $produit['photo_notice']?? '';
                    $daj   = isset($produit['date_ajout']) ? substr($produit['date_ajout'], 0, 10) : '';
                    $clbl  = $categories[$cat] ?? $cat;
                ?>
                    <tr data-product-id="<?php echo esc_attr($pid); ?>">
                        <td class="product-categorie"><?php echo esc_html($clbl); ?></td>
                        <td class="product-marque"><?php echo esc_html($marq); ?></td>
                        <td class="product-nom"><?php echo esc_html($nom); ?></td>
                        <td class="product-qty"><?php echo esc_html($qty); ?></td>
                        <td class="product-unit"><?php echo esc_html($unit); ?></td>
                        <td class="product-photos">
                            <?php if ($pface) : ?>
                                <img src="<?php echo esc_url($pface); ?>" alt="Face" onclick="showPhotoModal('<?php echo esc_js($pface); ?>')" title="Photo face">
                            <?php endif; ?>
                            <?php if ($pnot) : ?>
                                <img src="<?php echo esc_url($pnot); ?>" alt="Notice" onclick="showPhotoModal('<?php echo esc_js($pnot); ?>')" title="Photo notice">
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($daj); ?></td>
                        <td>
                            <div class="product-actions">
                                <button class="btn-edit"   onclick="editProduct(this)"   title="Modifier">✏️</button>
                                <button class="btn-delete" onclick="deleteProduct(this)" title="Supprimer">🗑️</button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php else : ?>
                <p id="no-products-msg"><em>Aucun produit enregistré. Ajoutez-en un ci-dessous.</em></p>
                <table id="products-table" style="display:none;">
                    <thead><tr>
                        <th>Catégorie</th><th>Marque</th><th>Nom</th>
                        <th>Qté</th><th>Unité</th><th>Photos</th><th>Date ajout</th><th>Actions</th>
                    </tr></thead>
                    <tbody></tbody>
                </table>
            <?php endif; ?>

            <!-- Modal photo plein écran -->
            <div id="photo-modal" class="photo-modal" onclick="closePhotoModal()">
                <div class="photo-modal-content" onclick="event.stopPropagation()">
                    <span class="photo-modal-close" onclick="closePhotoModal()">&times;</span>
                    <img id="photo-modal-img" src="" alt="Photo produit">
                </div>
            </div>

            <!-- ═══ FORMULAIRE AJOUT PRODUIT ══════════════════════════════════ -->
            <div class="product-form">
                <h3>➕ Ajouter un produit</h3>

                <!-- Ligne 1 : Catégorie / Marque / Nom -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new-cat">Catégorie <span style="color:#e74c3c">*</span></label>
                        <select id="new-cat">
                            <option value="">— Choisir —</option>
                            <?php foreach ($categories as $k => $lbl) : ?>
                                <option value="<?php echo esc_attr($k); ?>"><?php echo esc_html($lbl); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new-marque">Marque <span style="color:#e74c3c">*</span></label>
                        <input type="text" id="new-marque" placeholder="Ex : HTH, Bayrol…">
                    </div>
                    <div class="form-group">
                        <label for="new-nom">Nom du produit <span style="color:#e74c3c">*</span></label>
                        <input type="text" id="new-nom" placeholder="Ex : Chlore Action 5…">
                    </div>
                </div>

                <!-- Ligne 2 : Quantité / Unité / Commentaire -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="new-qty">Quantité</label>
                        <input type="number" id="new-qty" step="0.1" min="0" placeholder="Ex : 5">
                    </div>
                    <div class="form-group">
                        <label for="new-unit">Unité</label>
                        <select id="new-unit">
                            <option value="L">Litres (L)</option>
                            <option value="kg">Kilogrammes (kg)</option>
                            <option value="g">Grammes (g)</option>
                            <option value="ml">Millilitres (ml)</option>
                            <option value="comprimes">Comprimés</option>
                            <option value="unités">Unités</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="new-comment">Commentaire</label>
                        <input type="text" id="new-comment" placeholder="Notes, dosage habituel…">
                    </div>
                </div>

                <!-- Ligne 3 : Photos -->
                <div class="form-row-2">
                    <div class="form-group photo-upload-group">
                        <label>📷 Photo produit (face)</label>
                        <input type="file" id="new-photo-face" accept="image/*" capture="environment" onchange="previewPhoto(this,'prev-face')">
                        <img id="prev-face" class="photo-preview" style="display:none;" alt="">
                        <small style="color:#888;font-size:.8em">Optionnel — max 4 Mo</small>
                    </div>
                    <div class="form-group photo-upload-group">
                        <label>📄 Photo notice / dosage</label>
                        <input type="file" id="new-photo-notice" accept="image/*" capture="environment" onchange="previewPhoto(this,'prev-notice')">
                        <img id="prev-notice" class="photo-preview" style="display:none;" alt="">
                        <small style="color:#888;font-size:.8em">Optionnel — max 4 Mo</small>
                    </div>
                </div>

                <div style="text-align:center;margin-top:14px;">
                    <button class="btn-add-product" id="btn-add" onclick="addProduct()">
                        <span id="btn-add-label">➕ Ajouter ce produit</span>
                    </button>
                </div>
            </div>

            <p><a href="<?php echo home_url('/tableau-de-bord'); ?>">← Retour au tableau de bord</a></p>

            <!-- ═══ JAVASCRIPT PRODUITS ════════════════════════════════════════ -->
            <script>
            (function() {
                'use strict';

                const POOL_ID  = <?php echo (int)$post_id; ?>;
                const NONCE    = '<?php echo wp_create_nonce('wp_rest'); ?>';
                const API_BASE = '<?php echo esc_url(rest_url('sunny-pool/v1')); ?>';
                const PROD_URL = API_BASE + '/pool/' + POOL_ID + '/products';

                const CATS = <?php echo wp_json_encode($categories); ?>;
                const UNITS = ['L','kg','g','ml','comprimes','unités'];

                // ── Utilitaires ─────────────────────────────────────────────────
                function msg(text, type) {
                    const el = document.getElementById('product-message');
                    if (!el) return;
                    el.textContent = text;
                    el.className   = 'message-feedback message-' + type;
                    el.style.display = 'block';
                    el.scrollIntoView({behavior:'smooth', block:'nearest'});
                    setTimeout(() => el.style.display = 'none', 4500);
                }

                function esc(s) {
                    return String(s)
                        .replace(/&/g,'&amp;').replace(/</g,'&lt;')
                        .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                }

                function fileToB64(file) {
                    return new Promise((resolve) => {
                        if (!file || file.size === 0) { resolve(''); return; }
                        if (file.size > 4 * 1024 * 1024) {
                            msg('Image trop lourde (max 4 Mo) : ' + file.name, 'error');
                            resolve(''); return;
                        }
                        const r = new FileReader();
                        r.onload  = () => resolve(r.result);
                        r.onerror = () => { msg('Erreur lecture image','error'); resolve(''); };
                        r.readAsDataURL(file);
                    });
                }

                // Appel API générique
                function api(url, method, body) {
                    const opts = {
                        method,
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': NONCE }
                    };
                    if (body) opts.body = JSON.stringify(body);
                    return fetch(url, opts).then(r => {
                        if (!r.ok) return r.json().then(d => { throw new Error(d.message || 'HTTP ' + r.status); });
                        return r.json();
                    });
                }

                // ── Preview photo ────────────────────────────────────────────────
                window.previewPhoto = function(input, prevId) {
                    const el = document.getElementById(prevId);
                    if (!el || !input.files || !input.files[0]) return;
                    const r = new FileReader();
                    r.onload = e => { el.src = e.target.result; el.style.display = 'block'; };
                    r.readAsDataURL(input.files[0]);
                };

                // ── Modal photo ──────────────────────────────────────────────────
                window.showPhotoModal = function(src) {
                    document.getElementById('photo-modal-img').src = src;
                    document.getElementById('photo-modal').style.display = 'block';
                };
                window.closePhotoModal = function() {
                    document.getElementById('photo-modal').style.display = 'none';
                };
                document.addEventListener('keydown', e => { if (e.key === 'Escape') closePhotoModal(); });

                // ── Ajouter un produit ───────────────────────────────────────────
                window.addProduct = async function() {
                    const cat     = document.getElementById('new-cat').value.trim();
                    const marque  = document.getElementById('new-marque').value.trim();
                    const nom     = document.getElementById('new-nom').value.trim();
                    const qty     = parseFloat(document.getElementById('new-qty').value) || 0;
                    const unit    = document.getElementById('new-unit').value;
                    const comment = document.getElementById('new-comment').value.trim();

                    const errors = [];
                    if (!cat)   errors.push('Catégorie requise');
                    if (!marque) errors.push('Marque requise');
                    if (!nom)   errors.push('Nom requis');
                    if (errors.length) { msg('⚠️ ' + errors.join(' — '), 'error'); return; }

                    const btn   = document.getElementById('btn-add');
                    const label = document.getElementById('btn-add-label');
                    btn.disabled = true;
                    label.textContent = '⏳ Enregistrement…';

                    const faceB64   = await fileToB64(document.getElementById('new-photo-face').files[0]);
                    const noticeB64 = await fileToB64(document.getElementById('new-photo-notice').files[0]);

                    api(PROD_URL, 'POST', {
                        categorie: cat, marque, nom_produit: nom,
                        quantite: qty, unite: unit, commentaire: comment,
                        photo_face_base64: faceB64, photo_notice_base64: noticeB64
                    })
                    .then(data => {
                        msg('✅ Produit ajouté !', 'success');
                        addRow(data.data);
                        resetForm();
                    })
                    .catch(e => msg('❌ ' + e.message, 'error'))
                    .finally(() => { btn.disabled = false; label.textContent = '➕ Ajouter ce produit'; });
                };

                function resetForm() {
                    ['new-cat','new-marque','new-nom','new-qty','new-comment'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                    document.getElementById('new-unit').value = 'L';
                    ['prev-face','prev-notice'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) { el.src=''; el.style.display='none'; }
                    });
                    ['new-photo-face','new-photo-notice'].forEach(id => {
                        const el = document.getElementById(id);
                        if (el) el.value = '';
                    });
                }

                function addRow(p) {
                    const tbl  = document.getElementById('products-table');
                    const noP  = document.getElementById('no-products-msg');
                    if (noP) noP.style.display = 'none';
                    if (tbl) tbl.style.display = '';

                    const catLbl = CATS[p.categorie] || p.categorie || '—';
                    let photos   = '';
                    if (p.photo_face)   photos += `<img src="${esc(p.photo_face)}"   alt="Face"   onclick="showPhotoModal('${esc(p.photo_face)}')"   title="Face">`;
                    if (p.photo_notice) photos += `<img src="${esc(p.photo_notice)}" alt="Notice" onclick="showPhotoModal('${esc(p.photo_notice)}')" title="Notice">`;

                    const tr = document.createElement('tr');
                    tr.setAttribute('data-product-id', p.id);
                    tr.innerHTML = `
                        <td class="product-categorie">${esc(catLbl)}</td>
                        <td class="product-marque">${esc(p.marque||'')}</td>
                        <td class="product-nom">${esc(p.nom_produit||'')}</td>
                        <td class="product-qty">${esc(p.quantite||0)}</td>
                        <td class="product-unit">${esc(p.unite||'')}</td>
                        <td class="product-photos">${photos}</td>
                        <td>${esc((p.date_ajout||'').substring(0,10))}</td>
                        <td><div class="product-actions">
                            <button class="btn-edit"   onclick="editProduct(this)"   title="Modifier">✏️</button>
                            <button class="btn-delete" onclick="deleteProduct(this)" title="Supprimer">🗑️</button>
                        </div></td>`;
                    tbl.querySelector('tbody').appendChild(tr);
                }

                // ── Édition inline ───────────────────────────────────────────────
                window.editProduct = function(btn) {
                    const row = btn.closest('tr');
                    if (!row || row.classList.contains('editing')) return;
                    row.classList.add('editing');

                    const cells  = row.querySelectorAll('td');
                    const curCat = Object.keys(CATS).find(k => CATS[k] === cells[0].textContent.trim()) || '';
                    const catOpts = Object.entries(CATS)
                        .map(([k,v]) => `<option value="${esc(k)}" ${k===curCat?'selected':''}>${esc(v)}</option>`)
                        .join('');
                    const unitOpts = UNITS
                        .map(u => `<option value="${esc(u)}" ${u===cells[4].textContent.trim()?'selected':''}>${esc(u)}</option>`)
                        .join('');

                    // Sauvegarder le HTML original pour annulation
                    row.dataset.original = row.innerHTML;

                    cells[0].innerHTML = `<select class="ef">${catOpts}</select>`;
                    cells[1].innerHTML = `<input type="text"   class="ef" value="${esc(cells[1].textContent.trim())}" style="width:100%">`;
                    cells[2].innerHTML = `<input type="text"   class="ef" value="${esc(cells[2].textContent.trim())}" style="width:100%">`;
                    cells[3].innerHTML = `<input type="number" class="ef" value="${esc(cells[3].textContent.trim())}" step="0.1" min="0" style="width:60px">`;
                    cells[4].innerHTML = `<select class="ef">${unitOpts}</select>`;
                    cells[7].innerHTML = `<div class="product-actions">
                        <button class="btn-save"   onclick="saveProduct(this)"  title="Sauvegarder">💾</button>
                        <button class="btn-cancel" onclick="cancelEdit(this)"   title="Annuler">✕</button>
                    </div>`;
                };

                window.cancelEdit = function(btn) {
                    const row = btn.closest('tr');
                    if (!row) return;
                    row.classList.remove('editing');
                    if (row.dataset.original) row.innerHTML = row.dataset.original;
                };

                window.saveProduct = function(btn) {
                    const row       = btn.closest('tr');
                    const productId = row?.getAttribute('data-product-id');

                    if (!productId) {
                        msg('❌ ID produit introuvable — rechargez la page', 'error');
                        return;
                    }

                    const fields = row.querySelectorAll('.ef');
                    const body   = {
                        _action:     'update',
                        categorie:   fields[0].value,
                        marque:      fields[1].value.trim(),
                        nom_produit: fields[2].value.trim(),
                        quantite:    parseFloat(fields[3].value) || 0,
                        unite:       fields[4].value,
                    };

                    if (!body.nom_produit) { msg('⚠️ Nom du produit requis', 'error'); return; }

                    btn.disabled = true; btn.textContent = '⏳';

                    const actionUrl = PROD_URL + '/' + encodeURIComponent(productId) + '/action';

                    api(actionUrl, 'POST', body)
                    .then(() => { msg('✅ Produit mis à jour !', 'success'); location.reload(); })
                    .catch(e  => { msg('❌ ' + e.message, 'error'); btn.disabled=false; btn.textContent='💾'; });
                };

                // ── Supprimer un produit ─────────────────────────────────────────
                window.deleteProduct = function(btn) {
                    const row       = btn.closest('tr');
                    const productId = row?.getAttribute('data-product-id');
                    const nomEl     = row?.querySelector('.product-nom');
                    const nom       = nomEl ? nomEl.textContent.trim() : 'ce produit';

                    if (!productId) {
                        msg('❌ ID produit introuvable — rechargez la page puis réessayez', 'error');
                        return;
                    }

                    if (!confirm(`Supprimer "${nom}" ?\nCette action est irréversible.`)) return;

                    btn.disabled = true; btn.textContent = '⏳';

                    const actionUrl = PROD_URL + '/' + encodeURIComponent(productId) + '/action';

                    api(actionUrl, 'POST', { _action: 'delete' })
                    .then(() => {
                        msg('✅ Produit supprimé', 'success');
                        row.style.transition = 'opacity .3s';
                        row.style.opacity    = '0';
                        setTimeout(() => {
                            row.remove();
                            const tbody = document.querySelector('#products-table tbody');
                            if (tbody && tbody.children.length === 0) {
                                const noPMsg = document.getElementById('no-products-msg');
                                if (noPMsg) noPMsg.style.display = '';
                                document.getElementById('products-table').style.display = 'none';
                            }
                        }, 300);
                    })
                    .catch(e => { msg('❌ ' + e.message, 'error'); btn.disabled=false; btn.textContent='🗑️'; });
                };

            })();
            </script>
        </article>
    <?php endwhile; ?>
</div>

<?php
// Récupération du pied de page
get_footer();