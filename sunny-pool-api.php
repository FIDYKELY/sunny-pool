<?php
/**
 * API REST pour Sunny Pool App
 * Fournit les endpoints pour récupérer les données des piscines
 * Version 2.1 — Ajout endpoint /chat → bridge WordPress ↔ n8n
 */

if (!defined('ABSPATH')) exit;

// ========== CONSTANTES N8N — À CONFIGURER ==========
if (!defined('SUNNY_N8N_WEBHOOK_URL')) {
    define('SUNNY_N8N_WEBHOOK_URL', 'https://n8n.trouvezpourmoi.com/webhook/sunny-chat');
}
// Clé secrète que n8n envoie dans le header X-N8N-Secret lors du callback
if (!defined('SUNNY_N8N_CALLBACK_SECRET')) {
    define('SUNNY_N8N_CALLBACK_SECRET', 'a8F3kL9pQ2xZ7mN4rT6vW1yH5cJ8uB0s');
}

// ========== ENREGISTREMENT DES ENDPOINTS API ==========
add_action('rest_api_init', 'sunny_pool_register_api_routes');

function sunny_pool_register_api_routes() {
    error_log('[Sunny Pool] Enregistrement des routes API REST');
    // Récupérer toutes les piscines de l'utilisateur connecté
    register_rest_route('sunny-pool/v1', '/my-pools', [
        'methods' => 'GET',
        'callback' => 'sunny_pool_api_get_my_pools',
        'permission_callback' => 'sunny_pool_api_check_auth'
    ]);

    // Récupérer une piscine spécifique par ID
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'sunny_pool_api_get_pool',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Récupérer la météo d'une piscine
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/weather', [
        'methods' => 'GET',
        'callback' => 'sunny_pool_api_get_weather',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Récupérer les statistiques du dashboard
    register_rest_route('sunny-pool/v1', '/dashboard-stats', [
        'methods' => 'GET',
        'callback' => 'sunny_pool_api_get_dashboard_stats',
        'permission_callback' => 'sunny_pool_api_check_auth'
    ]);

    // Récupérer les produits d'une piscine
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/products', [
        'methods' => 'GET',
        'callback' => 'sunny_pool_api_get_products',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Ajouter un produit à une piscine
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/products', [
        'methods' => 'POST',
        'callback' => 'sunny_pool_api_add_product',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Modifier un produit (PUT natif — clients API)
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/products/(?P<product_key>[^/]+)', [
        'methods'             => 'PUT',
        'callback'            => 'sunny_pool_api_update_product',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id'          => ['validate_callback' => function($p) { return is_numeric($p); }],
            'product_key' => ['validate_callback' => function($p) { return !empty($p); }],
        ]
    ]);

    // Supprimer un produit (DELETE natif — clients API)
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/products/(?P<product_key>[^/]+)', [
        'methods'             => 'DELETE',
        'callback'            => 'sunny_pool_api_delete_product',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id'          => ['validate_callback' => function($p) { return is_numeric($p); }],
            'product_key' => ['validate_callback' => function($p) { return !empty($p); }],
        ]
    ]);

    // ── ACTION dispatcher (POST) ─────────────────────────────────────────────
    // Utilisé depuis le navigateur — évite les problèmes PUT/DELETE WordPress
    // URL  : POST /pool/{pool_id}/products/{product_id}/action
    // Body : { "_action": "update"|"delete", ...champs }
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)/products/(?P<product_key>[^/]+)/action', [
        'methods'             => 'POST',
        'callback'            => 'sunny_pool_api_product_action',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id'          => ['validate_callback' => function($p) { return is_numeric($p); }],
            'product_key' => ['validate_callback' => function($p) { return !empty($p); }],
        ]
    ]);

    // Créer une nouvelle piscine (POST)
    register_rest_route('sunny-pool/v1', '/pool', [
        'methods' => 'POST',
        'callback' => 'sunny_pool_api_create_pool',
        'permission_callback' => 'sunny_pool_api_check_auth'
    ]);

    // Mettre à jour une piscine (PUT)
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)', [
        'methods' => 'PUT',
        'callback' => 'sunny_pool_api_update_pool',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // Supprimer une piscine (DELETE)
    register_rest_route('sunny-pool/v1', '/pool/(?P<id>\d+)', [
        'methods' => 'DELETE',
        'callback' => 'sunny_pool_api_delete_pool',
        'permission_callback' => 'sunny_pool_api_check_pool_permission',
        'args' => [
            'id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                }
            ]
        ]
    ]);

    // ============================================================
    // NOUVEAU : Endpoint chat Sunny — Bridge WordPress ↔ n8n
    // POST /wp-json/sunny-pool/v1/chat
    // ============================================================
    register_rest_route('sunny-pool/v1', '/chat', [
        'methods'             => 'POST',
        'callback'            => 'sunny_pool_api_chat',
        'permission_callback' => 'sunny_pool_api_check_auth',
    ]);

    // ============================================================
    // NOUVEAU : Historique des conversations Sunny
    // GET /wp-json/sunny-pool/v1/chat/history?pool_id=123
    // ============================================================
    register_rest_route('sunny-pool/v1', '/chat/history', [
        'methods'             => 'GET',
        'callback'            => 'sunny_pool_api_get_chat_history',
        'permission_callback' => 'sunny_pool_api_check_auth',
        'args'                => [
            'pool_id' => [
                'validate_callback' => function($param, $request, $key) {
                    return is_numeric($param);
                },
                'default' => 0,
            ]
        ]
    ]);

    // ============================================================
    // Endpoint callback pour réponses asynchrones n8n
    // POST /wp-json/sunny-pool/v1/chat-callback
    // ============================================================
    register_rest_route('sunny-pool/v1', '/chat-callback', [
        'methods'             => 'POST',
        'callback'            => 'sunny_pool_api_chat_callback',
        'permission_callback' => 'sunny_pool_api_check_callback_auth',
    ]);

    // ============================================================
    // Endpoint polling pour vérifier les réponses en attente
    // GET /wp-json/sunny-pool/v1/chat/poll?conversation_id=xxx
    // ============================================================
    register_rest_route('sunny-pool/v1', '/chat/poll', [
        'methods'             => 'GET',
        'callback'            => 'sunny_pool_api_chat_poll',
        'permission_callback' => 'sunny_pool_api_check_auth',
        'args'                => [
            'conversation_id' => [
                'validate_callback' => function($param, $request, $key) {
                    return !empty($param);
                },
                'required' => true,
            ]
        ]
    ]);
}

// ========== FONCTIONS DE VÉRIFICATION DES PERMISSIONS ==========

/**
 * Vérifie si l'utilisateur est connecté (session PHP ou JWT Bearer)
 */
function sunny_pool_api_check_auth() {
    // Vérifier la session PHP avec nonce (depuis le shortcode)
    if (is_user_logged_in()) {
        return true;
    }
    
    // Sinon vérifier JWT Bearer
    return sunny_pool_api_validate_jwt_auth();
}

/**
 * Vérifie l'authentification du callback n8n (clé secrète ou IP)
 */
function sunny_pool_api_check_callback_auth($request) {
    // Option 1: Vérifier une clé secrète dans le header
    $secret_key = $request->get_header('X-N8N-Secret');
    $expected_key = defined('SUNNY_N8N_CALLBACK_SECRET') ? SUNNY_N8N_CALLBACK_SECRET : 'your-secret-key-here';
    
    if ($secret_key && $secret_key === $expected_key) {
        return true;
    }
    
    // Option 2: Vérifier l'IP (si n8n a une IP fixe)
    $remote_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $allowed_ips = defined('SUNNY_N8N_ALLOWED_IPS') ? explode(',', SUNNY_N8N_ALLOWED_IPS) : [];
    
    if (!empty($allowed_ips) && in_array($remote_ip, $allowed_ips)) {
        return true;
    }
    
    // Log pour debug
    error_log('[Sunny Callback] Auth échouée - IP: ' . $remote_ip . ' - Secret: ' . ($secret_key ? 'présent' : 'absent'));
    
    return new WP_Error(
        'rest_forbidden',
        'Clé d\'authentification invalide ou IP non autorisée',
        ['status' => 403]
    );
}

/**
 * Valide l'authentification JWT Bearer depuis le header Authorization
 */
function sunny_pool_api_validate_jwt_auth() {
    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '';
    error_log('[Sunny JWT] HTTP_AUTHORIZATION: ' . ($auth_header ? 'présent' : 'absent'));

    if (empty($auth_header)) {
        $auth_header = isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION']) ? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] : '';
        error_log('[Sunny JWT] REDIRECT_HTTP_AUTHORIZATION: ' . ($auth_header ? 'présent' : 'absent'));
    }

    if (empty($auth_header) || !preg_match('/Bearer\s+(\S+)/', $auth_header, $matches)) {
        error_log('[Sunny JWT] Header ne contient pas Bearer token');
        return false;
    }

    $token = $matches[1];
    error_log('[Sunny JWT] Token extrait, longueur: ' . strlen($token));
    
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        error_log('[Sunny JWT] Token malformé (pas 3 parties)');
        return false;
    }

    $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
    error_log('[Sunny JWT] Payload décodé: ' . ($payload ? 'oui' : 'non'));

    if (!$payload || !isset($payload['sub']) || !isset($payload['exp'])) {
        error_log('[Sunny JWT] Payload invalide ou champs manquants');
        return false;
    }

    error_log('[Sunny JWT] Exp: ' . $payload['exp'] . ' | Now: ' . time() . ' | Diff: ' . ($payload['exp'] - time()));
    if ($payload['exp'] < time()) {
        error_log('[Sunny JWT] Token expiré');
        return false;
    }

    $user = get_user_by('id', $payload['sub']);
    error_log('[Sunny JWT] User ' . $payload['sub'] . ': ' . ($user ? 'trouvé' : 'non trouvé'));
    if (!$user) {
        return false;
    }

    wp_set_current_user($user->ID);
    error_log('[Sunny JWT] Authentification réussie pour user ' . $user->ID);
    return true;
}

/**
 * Vérifie si l'utilisateur a accès à une piscine spécifique
 */
function sunny_pool_api_check_pool_permission($request) {
    if (!sunny_pool_api_check_auth()) {
        return false;
    }

    $pool_id  = $request->get_param('id');
    $user_id  = get_current_user_id();
    $owner_id = get_field('proprietaire', $pool_id);

    return ($user_id == $owner_id) || current_user_can('administrator');
}

// ========== FONCTIONS DE RÉCUPÉRATION DES DONNÉES ==========

/**
 * Récupère toutes les piscines de l'utilisateur connecté
 */
function sunny_pool_api_get_my_pools() {
    $user_id = get_current_user_id();

    $args = [
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [
            [
                'key'     => 'proprietaire',
                'value'   => $user_id,
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);
    $pools = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $pools[] = sunny_pool_format_pool_data(get_the_ID());
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response([
        'success' => true,
        'count'   => count($pools),
        'data'    => $pools
    ], 200);
}

/**
 * Récupère les données d'une piscine spécifique
 */
function sunny_pool_api_get_pool($request) {
    $pool_id = $request->get_param('id');

    if (!get_post($pool_id) || get_post_type($pool_id) !== 'piscine') {
        return new WP_REST_Response(['success' => false, 'message' => 'Piscine non trouvée'], 404);
    }

    return new WP_REST_Response(['success' => true, 'data' => sunny_pool_format_pool_data($pool_id)], 200);
}

/**
 * Récupère la météo d'une piscine
 */
function sunny_pool_api_get_weather($request) {
    $pool_id = $request->get_param('id');
    $lat     = get_field('latitude', $pool_id);
    $lon     = get_field('longitude', $pool_id);

    if (!$lat || !$lon) {
        return new WP_REST_Response(['success' => false, 'message' => 'Coordonnées GPS non disponibles'], 400);
    }

    $weather = sunny_get_weather($lat, $lon);

    if (!$weather) {
        return new WP_REST_Response(['success' => false, 'message' => 'Impossible de récupérer les données météo'], 500);
    }

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'latitude'  => $lat,
            'longitude' => $lon,
            'weather'   => $weather,
            'timestamp' => current_time('mysql')
        ]
    ], 200);
}

/**
 * Récupère les statistiques du dashboard
 */
function sunny_pool_api_get_dashboard_stats() {
    $user_id = get_current_user_id();

    $user_pools = new WP_Query([
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [['key' => 'proprietaire', 'value' => $user_id, 'compare' => '=']]
    ]);

    $total_volume = 0;
    $pool_types   = [];

    if ($user_pools->have_posts()) {
        while ($user_pools->have_posts()) {
            $user_pools->the_post();
            $volume = get_field('volume', get_the_ID());
            $type   = get_field('type_piscine', get_the_ID());
            if ($volume) $total_volume += floatval($volume);
            if ($type)   $pool_types[$type] = ($pool_types[$type] ?? 0) + 1;
        }
        wp_reset_postdata();
    }

    return new WP_REST_Response([
        'success' => true,
        'data'    => [
            'total_pools'    => $user_pools->found_posts,
            'total_volume_m3'=> round($total_volume, 2),
            'pool_types'     => $pool_types,
            'last_updated'   => current_time('mysql')
        ]
    ], 200);
}

/**
 * ══════════════════════════════════════════════════════════════════
 * GESTION PRODUITS — Stockage en post_meta JSON (bypass ACF)
 *
 * Pourquoi bypass ACF ?
 * ACF ne persiste que les sous-champs enregistrés dans son groupe.
 * Les champs id, categorie, marque, photo_face, photo_notice, commentaire
 * ne seront jamais perdus si on stocke le JSON directement.
 *
 * Clé meta : _sunny_produits  (préfixée _ = cachée dans l'UI WP)
 * Format   : JSON array de produits
 * ══════════════════════════════════════════════════════════════════
 */

/**
 * Lire les produits depuis la meta JSON (source de vérité)
 * Migre automatiquement les anciens produits ACF s'ils existent
 */
function sunny_get_produits($pool_id) {
    // Source principale : meta JSON
    $json = get_post_meta($pool_id, '_sunny_produits', true);
    if ($json) {
        $produits = json_decode($json, true);
        if (is_array($produits)) {
            return $produits;
        }
    }

    // Fallback : lire depuis ACF et migrer
    $acf_produits = get_field('produits', $pool_id);
    if (empty($acf_produits) || !is_array($acf_produits)) {
        return [];
    }

    // Migration : enrichir et sauvegarder en meta JSON
    $migrated = [];
    foreach ($acf_produits as $produit) {
        $migrated[] = [
            'id'              => !empty($produit['id']) ? $produit['id'] : ('prod_' . wp_generate_uuid4()),
            'categorie'       => $produit['categorie']        ?? '',
            'marque'          => $produit['marque']           ?? '',
            'nom_produit'     => $produit['nom_produit']      ?? ($produit['nom'] ?? ''),
            'quantite'        => floatval($produit['quantite'] ?? 0),
            'unite'           => $produit['unite']            ?? '',
            'photo_face'      => $produit['photo_face']       ?? '',
            'photo_notice'    => $produit['photo_notice']     ?? '',
            'commentaire'     => $produit['commentaire']      ?? '',
            'date_ajout'      => $produit['date_ajout']       ?? ($produit['date_mise_a_jour'] ?? current_time('mysql')),
            'date_mise_a_jour'=> $produit['date_mise_a_jour'] ?? '',
        ];
    }

    sunny_save_produits($pool_id, $migrated);
    error_log('[Sunny] Migration ' . count($migrated) . ' produits depuis ACF → meta JSON (pool ' . $pool_id . ')');
    return $migrated;
}

/**
 * Sauvegarder les produits en meta JSON
 */
function sunny_save_produits($pool_id, array $produits) {
    update_post_meta($pool_id, '_sunny_produits', wp_json_encode($produits, JSON_UNESCAPED_UNICODE));

    // Sync ACF pour compatibilité affichage (uniquement les champs que ACF connaît)
    $acf_compat = array_map(function($p) {
        return [
            'nom_produit' => $p['nom_produit'],
            'quantite'    => $p['quantite'],
            'unite'       => $p['unite'],
            'date_ajout'  => $p['date_ajout'],
        ];
    }, $produits);
    update_field('produits', $acf_compat, $pool_id);
}

/**
 * Récupère les produits d'une piscine (endpoint GET)
 */
function sunny_pool_api_get_products($request) {
    $pool_id  = (int) $request->get_param('id');
    $produits = sunny_get_produits($pool_id);

    $formatted = array_values(array_map(function($p) {
        return [
            'id'              => $p['id']               ?? '',
            'categorie'       => $p['categorie']        ?? '',
            'marque'          => $p['marque']           ?? '',
            'nom_produit'     => $p['nom_produit']      ?? '',
            'quantite'        => floatval($p['quantite'] ?? 0),
            'unite'           => $p['unite']            ?? '',
            'photo_face'      => $p['photo_face']       ?? '',
            'photo_notice'    => $p['photo_notice']     ?? '',
            'commentaire'     => $p['commentaire']      ?? '',
            'date_ajout'      => $p['date_ajout']       ?? '',
            'date_mise_a_jour'=> $p['date_mise_a_jour'] ?? '',
        ];
    }, $produits));

    return new WP_REST_Response(['success' => true, 'count' => count($formatted), 'data' => $formatted], 200);
}

/**
 * Ajoute un produit à une piscine
 */
function sunny_pool_api_add_product($request) {
    $pool_id = (int) $request->get_param('id');
    $params  = $request->get_json_params();

    $nom_produit = sanitize_text_field($params['nom_produit'] ?? '');
    if (empty($nom_produit)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Le champ nom_produit est requis'], 400);
    }

    $product_id = 'prod_' . wp_generate_uuid4();

    // Gérer les uploads de photos → médiathèque WP
    $photo_face   = '';
    $photo_notice = '';
    if (!empty($params['photo_face_base64'])) {
        $photo_face = sunny_pool_save_base64_image($params['photo_face_base64'], 'face_' . $product_id);
    }
    if (!empty($params['photo_notice_base64'])) {
        $photo_notice = sunny_pool_save_base64_image($params['photo_notice_base64'], 'notice_' . $product_id);
    }

    $new_product = [
        'id'              => $product_id,
        'categorie'       => sanitize_text_field($params['categorie']   ?? ''),
        'marque'          => sanitize_text_field($params['marque']       ?? ''),
        'nom_produit'     => $nom_produit,
        'quantite'        => floatval($params['quantite'] ?? 0),
        'unite'           => sanitize_text_field($params['unite']        ?? ''),
        'photo_face'      => $photo_face,
        'photo_notice'    => $photo_notice,
        'commentaire'     => sanitize_textarea_field($params['commentaire'] ?? ''),
        'date_ajout'      => current_time('mysql'),
        'date_mise_a_jour'=> '',
    ];

    $produits   = sunny_get_produits($pool_id);
    $produits[] = $new_product;
    sunny_save_produits($pool_id, $produits);

    error_log('[Sunny] Produit ajouté id=' . $product_id . ' pool=' . $pool_id);

    return new WP_REST_Response(['success' => true, 'message' => 'Produit ajouté', 'data' => $new_product], 201);
}

/**
 * Modifie un produit
 */
function sunny_pool_api_update_product($request) {
    $pool_id    = (int) $request->get_param('id');
    $product_id = $request->get_param('product_key');
    $params     = $request->get_json_params();

    $produits = sunny_get_produits($pool_id);
    $found    = false;

    foreach ($produits as &$produit) {
        if (($produit['id'] ?? '') === $product_id) {
            if (isset($params['categorie']))   $produit['categorie']   = sanitize_text_field($params['categorie']);
            if (isset($params['marque']))      $produit['marque']      = sanitize_text_field($params['marque']);
            if (isset($params['nom_produit'])) $produit['nom_produit'] = sanitize_text_field($params['nom_produit']);
            if (isset($params['quantite']))    $produit['quantite']    = floatval($params['quantite']);
            if (isset($params['unite']))       $produit['unite']       = sanitize_text_field($params['unite']);
            if (isset($params['commentaire'])) $produit['commentaire'] = sanitize_textarea_field($params['commentaire']);
            if (!empty($params['photo_face_base64'])) {
                $produit['photo_face']   = sunny_pool_save_base64_image($params['photo_face_base64'],   'face_'   . $product_id);
            }
            if (!empty($params['photo_notice_base64'])) {
                $produit['photo_notice'] = sunny_pool_save_base64_image($params['photo_notice_base64'], 'notice_' . $product_id);
            }
            $produit['date_mise_a_jour'] = current_time('mysql');
            $found = true;
            break;
        }
    }
    unset($produit);

    if (!$found) {
        return new WP_REST_Response(['success' => false, 'message' => 'Produit non trouvé (id: ' . $product_id . ')'], 404);
    }

    sunny_save_produits($pool_id, $produits);
    return new WP_REST_Response(['success' => true, 'message' => 'Produit mis à jour'], 200);
}

/**
 * Supprime un produit
 */
function sunny_pool_api_delete_product($request) {
    $pool_id    = (int) $request->get_param('id');
    $product_id = $request->get_param('product_key');

    $produits     = sunny_get_produits($pool_id);
    $new_produits = [];
    $found        = false;

    foreach ($produits as $produit) {
        if (($produit['id'] ?? '') === $product_id) {
            $found = true;
            // Supprimer les attachments WP
            foreach (['photo_face', 'photo_notice'] as $field) {
                if (!empty($produit[$field])) {
                    $att_id = attachment_url_to_postid($produit[$field]);
                    if ($att_id) wp_delete_attachment($att_id, true);
                }
            }
            continue;
        }
        $new_produits[] = $produit;
    }

    if (!$found) {
        return new WP_REST_Response(['success' => false, 'message' => 'Produit non trouvé (id: ' . $product_id . ')'], 404);
    }

    sunny_save_produits($pool_id, $new_produits);
    return new WP_REST_Response(['success' => true, 'message' => 'Produit supprimé'], 200);
}

/**
 * Action dispatcher — POST /pool/{id}/products/{key}/action
 * Contourne les problèmes PUT/DELETE sur certains serveurs
 */
function sunny_pool_api_product_action($request) {
    $params = $request->get_json_params();
    $action = sanitize_text_field($params['_action'] ?? '');

    if ($action === 'update') return sunny_pool_api_update_product($request);
    if ($action === 'delete') return sunny_pool_api_delete_product($request);

    return new WP_REST_Response([
        'success' => false,
        'message' => 'Action inconnue. Utilisez "_action": "update" ou "delete".'
    ], 400);
}

/**
 * Sauvegarde une image base64 dans la médiathèque WordPress
 * Retourne l'URL ou chaîne vide en cas d'échec
 */
function sunny_pool_save_base64_image($base64_string, $filename_prefix) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $mime_type = 'image/jpeg';
    if (preg_match('/^data:(image\/[^;]+);base64,(.+)$/s', $base64_string, $m)) {
        $mime_type     = $m[1];
        $base64_string = $m[2];
    }

    $base64_string = preg_replace('/\s+/', '', $base64_string);
    $image_data    = base64_decode($base64_string);
    if (!$image_data) return '';

    $ext      = str_replace('image/', '', $mime_type);
    $ext      = ($ext === 'jpeg') ? 'jpg' : $ext;
    $tmp_file = wp_tempnam($filename_prefix . '.' . $ext);
    file_put_contents($tmp_file, $image_data);

    $file_array = [
        'name'     => sanitize_file_name($filename_prefix . '_' . time() . '.' . $ext),
        'tmp_name' => $tmp_file,
        'type'     => $mime_type,
        'error'    => 0,
        'size'     => strlen($image_data),
    ];

    $attachment_id = media_handle_sideload($file_array, 0, $filename_prefix);
    @unlink($tmp_file);

    if (is_wp_error($attachment_id)) {
        error_log('[Sunny] Erreur upload image : ' . $attachment_id->get_error_message());
        return '';
    }

    return wp_get_attachment_url($attachment_id);
}

// ============================================================
// ENDPOINT PRINCIPAL DU CHAT SUNNY — Bridge WordPress ↔ n8n
// POST /wp-json/sunny-pool/v1/chat
// ============================================================

/**
 * Envoie le message à n8n (mode asynchrone avec callback).
 *
 * n8n reçoit : session_id, conversation_id, callback_url, message,
 *              image_base64 (base64 pur, sans préfixe data:),
 *              latitude, longitude, pool{type,volume,filtre,traitement},
 *              analyse{ph,chlore,tac,stabilisant,temperature}, produits[]
 *
 * n8n rappelle WordPress sur /chat-callback avec : conversation_id + response
 * Le frontend poll /chat/poll?conversation_id=xxx jusqu'à obtenir la réponse.
 */
function sunny_pool_api_chat($request) {
    global $wpdb; // ← Nécessaire pour l'accès base de données
    $user_id = get_current_user_id();
    $params  = $request->get_json_params();

    // ── Validation ──────────────────────────────────────────────
    $message         = sanitize_text_field($params['message'] ?? '');
    $image_base64_raw= $params['image_base64'] ?? '';   // peut contenir le préfixe data:image/...
    $analyse_user    = $params['analyse']      ?? [];
    $conversation_id = sanitize_text_field($params['conversation_id'] ?? '');
    $image_type      = sanitize_text_field($params['image_type'] ?? 'general');

    // ── 1. Options pour contrôler quelles données sont injectées dans n8n ─
    $data_options_in = $params['data_options'] ?? [];
    $data_options_in = is_array($data_options_in) ? $data_options_in : [];

    // Valeurs optimisées : ne charger que si explicitement demandé
    $opt_meteo       = !empty($data_options_in['meteo']);
    $opt_historique  = !empty($data_options_in['historique']);
    $opt_produits    = !empty($data_options_in['produits']);
    $opt_alertes     = !empty($data_options_in['alertes']);
    $opt_planning    = !empty($data_options_in['planning']);
    $opt_coordonnees = !empty($data_options_in['coordonnees']);

    if (empty($message) && empty($image_base64_raw)) {
        return new WP_REST_Response([
            'success' => false,
            'message' => "Le champ 'message' ou 'image_base64' est requis."
        ], 400);
    }

    // ── Nettoyer l'image : retirer le préfixe data:image/...;base64, ──
    // n8n (et OpenAI Vision) attendent la chaîne base64 PURE
    $image_base64 = '';
    if (!empty($image_base64_raw)) {
        if (preg_match('/^data:image\/[^;]+;base64,(.+)$/s', $image_base64_raw, $m)) {
            $image_base64 = $m[1];
        } else {
            $image_base64 = $image_base64_raw; // déjà pur
        }
    }

    // ── Récupérer la piscine ─────────────────────────────────────
    $pool_id_param = isset($params['pool_id']) ? intval($params['pool_id']) : 0;

    $pools = get_posts([
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [['key' => 'proprietaire', 'value' => $user_id, 'compare' => '=']],
    ]);

    $pool_data        = [];
    $lat              = -18.9137; // défaut Antananarivo
    $lon              = 47.5361;
    $selected_pool_id = 0;
    $pool_post        = null;
    $produits_noms    = [];
    $produits_data    = []; // Tableau complet des produits pour n8n

    // ── Fonction helper : convertir URL image en base64 ─────────
    function sunny_url_to_base64($url, $max_size_mb = 4) {
        if (empty($url)) return '';

        // Convertir URL en chemin local si c'est une URL du site
        $upload_dir = wp_upload_dir();
        $base_url = $upload_dir['baseurl'];

        if (strpos($url, $base_url) === 0) {
            $relative_path = substr($url, strlen($base_url));
            $file_path = $upload_dir['basedir'] . $relative_path;
        } else {
            // Télécharger l'image si URL externe
            $temp_file = download_url($url, 10);
            if (is_wp_error($temp_file)) return '';
            $file_path = $temp_file;
        }

        if (!file_exists($file_path)) return '';

        $file_size = filesize($file_path);
        if ($file_size > $max_size_mb * 1024 * 1024) return ''; // Trop grand

        $file_data = file_get_contents($file_path);
        $file_type = wp_check_filetype($file_path)['type'] ?: 'image/jpeg';

        // Nettoyer le fichier temporaire si c'était une URL externe
        if (isset($temp_file) && $temp_file === $file_path) {
            @unlink($temp_file);
        }

        // Retourner base64 pur (sans préfixe data URI)
        return base64_encode($file_data);
    }

    if (!empty($pools)) {
        if ($pool_id_param > 0) {
            foreach ($pools as $p) {
                if ($p->ID == $pool_id_param) { $selected_pool_id = $p->ID; $pool_post = $p; break; }
            }
        }
        if (!$selected_pool_id) { $pool_post = $pools[0]; $selected_pool_id = $pool_post->ID; }

        $pool_data = [
            'type'        => get_field('type_piscine',    $selected_pool_id),
            'volume'      => floatval(get_field('volume', $selected_pool_id)),
            'filtre'      => get_field('type_filtration', $selected_pool_id),
            'traitement'  => get_field('type_traitement', $selected_pool_id),
            'equipements' => get_field('equipements',     $selected_pool_id) ?: [],
        ];

        $lat_s = get_field('latitude',  $selected_pool_id);
        $lon_s = get_field('longitude', $selected_pool_id);
        if ($lat_s && $lon_s) { $lat = floatval($lat_s); $lon = floatval($lon_s); }

        // ── 2. Coordonnées : reset à 0 si météo non demandée ─────────────────
        // Cela évite à n8n de lancer l'API Open-Meteo inutilement
        if (!$opt_meteo || !$opt_coordonnees) {
            $lat = 0;
            $lon = 0;
        }

        // ── 3. Produits (Uniquement si demandé) ───────────────────────────────
        if ($opt_produits) {
            $produits_json = get_post_meta($selected_pool_id, '_sunny_produits', true);
            $produits_list = $produits_json ? json_decode($produits_json, true) : [];

            // Fallback ACF si pas encore migré
            if (empty($produits_list)) {
                $produits_acf = get_field('produits', $selected_pool_id);
                $produits_list = !empty($produits_acf) && is_array($produits_acf) ? $produits_acf : [];
            }

            foreach ($produits_list as $p) {
                $nom = $p['nom_produit'] ?? ($p['nom'] ?? '');
                if ($nom) {
                    $label = sunny_pool_get_product_label($nom);
                    $qty   = $p['quantite'] ?? '';
                    $unite = $p['unite']    ?? '';
                    $produits_noms[] = trim($label . ($qty ? " ({$qty} {$unite})" : ''));

                    // Données complètes pour n8n (conversion base64 lourde)
                    $photo_face_url   = $p['photo_face'] ?? '';
                    $photo_notice_url = $p['photo_notice'] ?? '';

                    $produits_data[] = [
                        'nom_produit'        => $nom,
                        'label'              => $label,
                        'categorie'          => $p['categorie'] ?? '',
                        'marque'             => $p['marque'] ?? '',
                        'quantite'           => $qty,
                        'unite'              => $unite,
                        'date_ajout'         => $p['date_ajout'] ?? '',
                        'commentaire'        => $p['commentaire'] ?? '',
                        'photo_face_url'     => $photo_face_url,
                        'photo_notice_url'   => $photo_notice_url,
                        'photo_face_base64'  => sunny_url_to_base64($photo_face_url),
                        'photo_notice_base64'=> sunny_url_to_base64($photo_notice_url),
                    ];
                }
            }
        }

        // ── Récupérer une image de la piscine en base64 ──────────────
        // [DÉSACTIVÉ] : L'image n'est plus envoyée automatiquement pour éviter
        // la lenteur à chaque message. L'image est maintenant analysée uniquement
        // lorsque l'utilisateur envoie volontairement une photo depuis le chat.
        // L'utilisateur veut analyser une photo ? Il l'envoie explicitement.
        /*
        if (empty($image_base64)) {
            $photos = get_field('photos_de_la_piscine', $selected_pool_id);

            if (!empty($photos) && is_array($photos)) {
                $first_photo = $photos[0];
                $image_id = is_array($first_photo) ? ($first_photo['ID'] ?? 0) : intval($first_photo);

                if ($image_id) {
                    $image_path = get_attached_file($image_id);

                    if ($image_path && file_exists($image_path)) {
                        $image_size = filesize($image_path);

                        if ($image_size < 5 * 1024 * 1024) {
                            $file_data = file_get_contents($image_path);
                            $file_type = wp_check_filetype($image_path)['type'] ?: 'image/jpeg';
                            $image_base64_raw = 'data:' . $file_type . ';base64,' . base64_encode($file_data);

                            if (preg_match('/^data:image\/[^;]+;base64,(.+)$/s', $image_base64_raw, $m)) {
                                $image_base64 = $m[1];
                            }

                            error_log('[Sunny Chat] Image pool récupérée: ' . round($image_size / 1024, 2) . ' Ko');
                        }
                    }
                }
            }
        }
        */
    }

    // ── session_id et conversation_id ────────────────────────────
    $session_id = 'wp-user-' . $user_id . '-pool-' . $selected_pool_id;

    // Le frontend peut fournir son propre conversation_id ; sinon on en génère un
    $final_conversation_id = $conversation_id
        ?: ('conv-' . $user_id . '-' . $selected_pool_id . '-' . time());

    // ── callback_url que n8n utilisera pour renvoyer la réponse ──
    $callback_url = rest_url('sunny-pool/v1/chat-callback');

    // ── Normaliser l'analyse ──────────────────────────────────────
    $analyse = [];
    foreach (['ph', 'chlore', 'tac', 'stabilisant', 'temperature'] as $f) {
        $analyse[$f] = isset($analyse_user[$f]) && $analyse_user[$f] !== ''
            ? floatval($analyse_user[$f])
            : null;
    }

    // ── 4. Historique (Uniquement si demandé) ─────────────────────
    $history_formatted = [];
    if ($opt_historique) {
        $table_name = $wpdb->prefix . 'sunny_chat_messages';
        $messages_history = $wpdb->get_results($wpdb->prepare(
            "SELECT message, response, created_at FROM $table_name
             WHERE pool_id = %d AND user_id = %d
             ORDER BY created_at DESC LIMIT 20",
            $selected_pool_id, $user_id
        ), ARRAY_A);

        if (!empty($messages_history)) {
            // Inverser pour avoir l'ordre chronologique (ancien → récent)
            $messages_history = array_reverse($messages_history);
            foreach ($messages_history as $msg) {
                if (!empty($msg['message']) && $msg['message'] !== '[callback]') {
                    $history_formatted[] = ['role' => 'user', 'content' => '[HISTORIQUE] ' . $msg['message']];
                }
                if (!empty($msg['response'])) {
                    $history_formatted[] = ['role' => 'assistant', 'content' => '[HISTORIQUE] ' . $msg['response']];
                }
            }
        }
    }

    // ── 5. Payload Final (Nettoyé) ────────────────────────────────
    $n8n_payload = [
        'session_id'       => $session_id,
        'conversation_id'  => $final_conversation_id,
        'callback_url'     => $callback_url,
        'message'          => $message,
        'image_base64'     => $image_base64,     // base64 pur (vide si pas d'image)
        'image_type'       => $image_type,       // 'water' | 'product' | 'pool' | 'general'
        'latitude'         => $lat,              // 0 si pas de météo
        'longitude'        => $lon,              // 0 si pas de météo
        'pool'             => $pool_data,
        'analyse'          => $analyse,
        'produits'         => $produits_data,    // Vide si option décochée
        'produits_summary' => $produits_noms,    // Vide si option décochée
        'history'          => $history_formatted, // Vide si option décochée
        'data_options'     => $data_options_in,  // On transmet les choix pour que n8n s'adapte
        'wp_pool_id'       => $selected_pool_id,
        'wp_user_id'       => $user_id,
    ];

    error_log('[Sunny Chat] → n8n | conv: ' . $final_conversation_id . ' | img: ' . (empty($image_base64) ? 'non' : round(strlen($image_base64)/1024) . 'Ko'));

    // ── Sauvegarder le message en statut 'pending' ───────────────
    // (la réponse sera mise à jour par le callback)
    sunny_pool_save_chat_message(
        $user_id, $selected_pool_id,
        $message, '',                     // réponse vide pour l'instant
        !empty($image_base64),
        $session_id, $final_conversation_id, 'pending'
    );

    // ── Appel webhook n8n (fire-and-forget) ──────────────────────
    $n8n_response = wp_remote_post(SUNNY_N8N_WEBHOOK_URL, [
        'headers' => ['Content-Type' => 'application/json'],
        'body'    => wp_json_encode($n8n_payload),
        'timeout' => 10,   // court : on n'attend pas la réponse IA, juste l'accusé n8n
    ]);

    if (is_wp_error($n8n_response)) {
        error_log('[Sunny Chat] Erreur n8n : ' . $n8n_response->get_error_message());
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Le service Sunny est temporairement indisponible.'
        ], 503);
    }

    $http_code = wp_remote_retrieve_response_code($n8n_response);
    if ($http_code >= 400) {
        error_log('[Sunny Chat] n8n HTTP ' . $http_code . ' : ' . wp_remote_retrieve_body($n8n_response));
        return new WP_REST_Response([
            'success' => false,
            'message' => 'Le service Sunny a retourné une erreur (' . $http_code . ').'
        ], 502);
    }

    // ── Répondre immédiatement au frontend : "pending" ───────────
    // Le JS va poller /chat/poll?conversation_id=xxx
    return new WP_REST_Response([
        'success'         => true,
        'response'        => 'pending',
        'conversation_id' => $final_conversation_id,
        'pool_found'      => !empty($pools),
        'user_id'         => $user_id,
    ], 200);
}

/**
 * Sauvegarde ou met à jour un échange chat dans la table SQL
 *
 * En mode async, on INSERT d'abord avec status='pending' (réponse vide),
 * puis le callback UPDATE la ligne avec la réponse et status='completed'.
 */
function sunny_pool_save_chat_message($user_id, $pool_id, $message, $response, $has_image = false, $session_id = '', $conversation_id = '', $status = 'completed', $analyse_extraite = null, $score_eau = null, $alertes_json = null) {
    global $wpdb;
    if (!$pool_id) return;

    $table_name = $wpdb->prefix . 'sunny_chat_messages';

    // Si la ligne existe déjà pour cette conversation_id, on met à jour
    if ($conversation_id) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE conversation_id = %s LIMIT 1",
            $conversation_id
        ));
        if ($existing) {
            $update_data = [
                    'response'        => wp_strip_all_tags($response),
                    'status'          => $status,
                    'updated_at'      => current_time('mysql'),
                ];
                if ($analyse_extraite !== null) $update_data['analyse_extraite'] = wp_json_encode($analyse_extraite);
                if ($score_eau        !== null) $update_data['score_eau']         = intval($score_eau);
                if ($alertes_json     !== null) $update_data['alertes_json']      = wp_json_encode($alertes_json);
            $wpdb->update($table_name, $update_data, ['id' => $existing]);
            return;
        }
    }

    // Sinon INSERT
    $insert_data = [
        'user_id'         => $user_id,
        'pool_id'         => $pool_id,
        'session_id'      => $session_id,
        'conversation_id' => $conversation_id,
        'message'         => wp_strip_all_tags($message),
        'response'        => wp_strip_all_tags($response),
        'has_image'       => $has_image ? 1 : 0,
        'status'          => $status,
        'created_at'      => current_time('mysql'),
    ];
    if ($analyse_extraite !== null) $insert_data['analyse_extraite'] = wp_json_encode($analyse_extraite);
    if ($score_eau        !== null) $insert_data['score_eau']         = intval($score_eau);
    if ($alertes_json     !== null) $insert_data['alertes_json']      = wp_json_encode($alertes_json);
    $wpdb->insert($table_name, $insert_data);

    // Garder seulement les 50 derniers messages par piscine
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE pool_id = %d AND id NOT IN (
            SELECT id FROM (
                SELECT id FROM $table_name WHERE pool_id = %d ORDER BY created_at DESC LIMIT 50
            ) as temp
        )",
        $pool_id, $pool_id
    ));
}

/**
 * Callback appelé par n8n une fois que l'IA a répondu
 * POST /wp-json/sunny-pool/v1/chat-callback
 *
 * Body attendu depuis n8n :
 * { "conversation_id": "conv-42-123-1700000000", "response": "Texte de Sunny..." }
 *
 * Authentifié par le header X-N8N-Secret (défini dans SUNNY_N8N_CALLBACK_SECRET)
 */
function sunny_pool_api_chat_callback($request) {
    global $wpdb;
    $params = $request->get_json_params();

    $conversation_id  = sanitize_text_field($params['conversation_id'] ?? '');
    $response         = wp_kses_post($params['response'] ?? '');
    $analyse_extraite = $params['analyse_extraite'] ?? null;  // array ou null
    $score_eau        = isset($params['score_eau']) ? intval($params['score_eau']) : null;
    $alertes          = $params['alertes'] ?? null;            // array ou null

    error_log('[Sunny Callback] conv: ' . $conversation_id
        . ' | img_analyse: ' . ($analyse_extraite ? 'oui' : 'non')
        . ' | score: ' . ($score_eau !== null ? $score_eau : 'N/A')
        . ' | réponse: ' . substr($response, 0, 60) . '...');

    if (empty($conversation_id) || empty($response)) {
        return new WP_REST_Response(['success' => false, 'message' => 'conversation_id et response requis'], 400);
    }

    // Retrouver la ligne pending dans la DB (insérée par /chat)
    $table = $wpdb->prefix . 'sunny_chat_messages';
    $row   = $wpdb->get_row($wpdb->prepare(
        "SELECT id, user_id, pool_id FROM $table WHERE conversation_id = %s LIMIT 1",
        $conversation_id
    ));

    if ($row) {
        $update_data = [
            'response'   => wp_strip_all_tags($response),
            'status'     => 'completed',
            'updated_at' => current_time('mysql'),
        ];
        if ($analyse_extraite !== null) $update_data['analyse_extraite'] = wp_json_encode($analyse_extraite);
        if ($score_eau        !== null) $update_data['score_eau']         = $score_eau;
        if ($alertes          !== null) $update_data['alertes_json']      = wp_json_encode($alertes);

        $wpdb->update($table, $update_data, ['id' => $row->id]);
        error_log('[Sunny Callback] Ligne id=' . $row->id . ' mise à jour OK');
    } else {
        $parts   = explode('-', $conversation_id);
        $user_id = isset($parts[1]) ? intval($parts[1]) : 0;
        $pool_id = isset($parts[2]) ? intval($parts[2]) : 0;

        if ($user_id && $pool_id) {
            sunny_pool_save_chat_message(
                $user_id, $pool_id,
                '[callback]', $response,
                false, '', $conversation_id, 'completed',
                $analyse_extraite, $score_eau, $alertes
            );
        }
        error_log('[Sunny Callback] Ligne introuvable → INSERT fallback user=' . $user_id . ' pool=' . $pool_id);
    }

    return new WP_REST_Response(['success' => true, 'message' => 'Réponse enregistrée'], 200);
}

/**
 * Polling pour vérifier si une réponse est arrivée (flux asynchrone)
 * GET /wp-json/sunny-pool/v1/chat/poll?conversation_id=xxx
 */
function sunny_pool_api_chat_poll($request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $conversation_id = $request->get_param('conversation_id');

    error_log('[Sunny Poll] Recherche conversation_id: ' . $conversation_id . ', user_id: ' . $user_id);

    $table_name = $wpdb->prefix . 'sunny_chat_messages';

    // Chercher un message avec cette conversation_id qui a une réponse
    $message = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name 
        WHERE conversation_id = %s 
        AND user_id = %d 
        AND (response IS NOT NULL AND response != '') 
        AND response != '[async]'
        ORDER BY created_at DESC 
        LIMIT 1",
        $conversation_id,
        $user_id
    ), ARRAY_A);

    if ($message) {
        error_log('[Sunny Poll] Réponse trouvée pour ' . $conversation_id);

        // Décoder les champs JSON stockés
        $analyse_decoded = !empty($message['analyse_extraite'])
            ? json_decode($message['analyse_extraite'], true)
            : null;
        $alertes_decoded = !empty($message['alertes_json'])
            ? json_decode($message['alertes_json'], true)
            : [];

        return new WP_REST_Response([
            'success'          => true,
            'found'            => true,
            'response'         => $message['response'],
            'analyse_extraite' => $analyse_decoded,   // null ou {ph, chlore, tac, ...}
            'score_eau'        => $message['score_eau'] !== null ? intval($message['score_eau']) : null,
            'alertes'          => $alertes_decoded,
            'has_image'        => (bool) $message['has_image'],
            'status'           => $message['status'],
            'created_at'       => $message['created_at'],
        ], 200);
    }

    error_log('[Sunny Poll] Pas encore de réponse pour ' . $conversation_id);

    // Pas encore de réponse
    return new WP_REST_Response([
        'success' => true,
        'found'   => false,
        'message' => 'En attente de réponse...',
    ], 200);
}

/**
 * Retourne l'historique des conversations Sunny depuis la table SQL
 * GET /wp-json/sunny-pool/v1/chat/history?pool_id=123
 */
function sunny_pool_api_get_chat_history($request) {
    global $wpdb;
    $user_id = get_current_user_id();
    $pool_id_param = $request->get_param('pool_id');

    // Récupérer toutes les piscines de l'utilisateur
    $pools = get_posts([
        'post_type'      => 'piscine',
        'posts_per_page' => -1,
        'meta_query'     => [
            ['key' => 'proprietaire', 'value' => $user_id, 'compare' => '=']
        ]
    ]);

    if (empty($pools)) {
        return new WP_REST_Response(['success' => true, 'count' => 0, 'data' => []], 200);
    }

    // Si pool_id spécifié, vérifier qu'il appartient à l'utilisateur
    $pool_id = 0;
    if ($pool_id_param) {
        foreach ($pools as $pool) {
            if ($pool->ID == $pool_id_param) {
                $pool_id = $pool->ID;
                break;
            }
        }
    }
    // Si pas de pool_id valide, prendre la première
    if (!$pool_id) {
        $pool_id = $pools[0]->ID;
    }

    // Lire depuis la table SQL
    $table_name = $wpdb->prefix . 'sunny_chat_messages';
    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE pool_id = %d ORDER BY created_at ASC LIMIT 50",
        $pool_id
    ), ARRAY_A);

    // Formater pour compatibilité avec le frontend
    $formatted = [];
    foreach ($history as $row) {
        $formatted[] = [
            'date'             => $row['created_at'],
            'message'          => $row['message'],
            'response'         => $row['response'],
            'analyse_extraite' => !empty($row['analyse_extraite']) ? json_decode($row['analyse_extraite'], true) : null,
            'score_eau'        => $row['score_eau'] !== null ? intval($row['score_eau']) : null,
            'alertes'          => !empty($row['alertes_json']) ? json_decode($row['alertes_json'], true) : [],
            'has_image'        => (bool) $row['has_image'],
            'status'           => $row['status'],
        ];
    }

    // Retourner du plus récent au plus ancien
    $history_reversed = array_reverse($formatted);

    return new WP_REST_Response([
        'success' => true,
        'count'   => count($history_reversed),
        'data'    => $history_reversed,
    ], 200);
}

// ========== FONCTIONS DE MODIFICATION DES DONNÉES ==========

/**
 * Upload une image base64 et l'attache à un post
 * @param string $base64_data Image en base64 (avec ou sans préfixe data:image)
 * @param int $parent_post_id ID du post parent
 * @return int|WP_Error ID de l'attachment ou erreur
 */
function sunny_pool_upload_base64_image($base64_data, $parent_post_id = 0) {
    // Nettoyer le base64 (retirer le préfixe data:image si présent)
    if (preg_match('/^data:image\/[^;]+;base64,(.+)$/s', $base64_data, $m)) {
        $base64_clean = $m[1];
    } else {
        $base64_clean = $base64_data;
    }

    $base64_clean = preg_replace('/\s+/', '', $base64_clean);

    if (empty($base64_clean)) {
        return new WP_Error('empty_image', 'Image base64 vide');
    }

    $image_data = base64_decode($base64_clean);
    if ($image_data === false) {
        return new WP_Error('decode_error', 'Impossible de décoder l\'image base64');
    }

    // Limite de taille : 10 Mo
    if (strlen($image_data) > 10 * 1024 * 1024) {
        return new WP_Error('image_too_large', 'Image trop volumineuse (max 10 Mo)');
    }

    // Détecter le type MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_buffer($finfo, $image_data);
    finfo_close($finfo);

    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    if (!in_array($mime_type, $allowed_types)) {
        return new WP_Error('invalid_type', 'Type d\'image non supporté. Utilisez JPEG, PNG, GIF ou WebP');
    }

    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime_type];
    $filename = 'piscine_' . time() . '_' . wp_rand(1000, 9999) . '.' . $ext;

    $upload_dir = wp_upload_dir();
    $file_path = $upload_dir['path'] . '/' . $filename;
    $file_url = $upload_dir['url'] . '/' . $filename;

    if (file_put_contents($file_path, $image_data) === false) {
        return new WP_Error('write_error', 'Impossible de sauvegarder l\'image');
    }

    // Créer l'attachment WordPress
    $attachment = [
        'post_mime_type' => $mime_type,
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit'
    ];

    $attach_id = wp_insert_attachment($attachment, $file_path, $parent_post_id);

    if (is_wp_error($attach_id)) {
        @unlink($file_path);
        return $attach_id;
    }

    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

/**
 * Crée une nouvelle piscine via API
 */
function sunny_pool_api_create_pool($request) {
    $params = $request->get_json_params();

    $required_fields = ['nom_piscine', 'type_piscine', 'longueur', 'largeur', 'profondeur', 'adresse', 'code_postal', 'ville', 'pays'];
    foreach ($required_fields as $field) {
        if (empty($params[$field])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Champ obligatoire manquant : ' . $field], 400);
        }
    }

    $post_title = !empty($params['nom_piscine']) ? sanitize_text_field($params['nom_piscine']) : 'Piscine ' . (['enterree' => 'enterrée', 'hors-sol' => 'hors-sol'][$params['type_piscine']] ?? $params['type_piscine']);

    $post_id = wp_insert_post([
        'post_title'  => $post_title,
        'post_type'   => 'piscine',
        'post_status' => 'publish',
        'post_author' => get_current_user_id()
    ]);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['success' => false, 'message' => 'Erreur lors de la création de la piscine'], 500);
    }

    $longueur   = floatval($params['longueur']);
    $largeur    = floatval($params['largeur']);
    $profondeur = floatval($params['profondeur']);
    $volume     = $longueur * $largeur * $profondeur;

    update_field('type_piscine',    sanitize_text_field($params['type_piscine']), $post_id);
    update_field('longueur',        $longueur,    $post_id);
    update_field('largeur',         $largeur,     $post_id);
    update_field('profondeur',      $profondeur,  $post_id);
    update_field('volume',          $volume,      $post_id);
    update_field('type_filtration', sanitize_text_field($params['type_filtration'] ?? ''), $post_id);
    update_field('type_traitement', sanitize_text_field($params['type_traitement'] ?? ''), $post_id);
    update_field('equipements',     $params['equipements'] ?? [], $post_id);
    update_field('proprietaire',    get_current_user_id(), $post_id);

    $adresse_complete = sanitize_text_field($params['adresse']) . ', ' .
                        sanitize_text_field($params['code_postal']) . ' ' .
                        sanitize_text_field($params['ville']) . ', ' .
                        sanitize_text_field($params['pays']);

    update_field('adresse_textuelle', $adresse_complete, $post_id);

    $coordinates = sunny_geocode_address($adresse_complete);
    if ($coordinates) {
        update_field('latitude',  $coordinates['lat'], $post_id);
        update_field('longitude', $coordinates['lon'], $post_id);
    }

    if (!empty($params['produits']) && is_array($params['produits'])) {
        update_field('produits', $params['produits'], $post_id);
    }

    // ── Gestion de l'image de la piscine (base64) ───────────────
    if (!empty($params['image_base64'])) {
        $photo_id = sunny_pool_upload_base64_image($params['image_base64'], $post_id);
        if ($photo_id && !is_wp_error($photo_id)) {
            update_field('photos_de_la_piscine', [$photo_id], $post_id);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Piscine créée avec succès',
        'data'    => ['id' => $post_id, 'pool' => sunny_pool_format_pool_data($post_id)]
    ], 201);
}

/**
 * Met à jour une piscine existante
 */
function sunny_pool_api_update_pool($request) {
    $pool_id = $request->get_param('id');
    $params  = $request->get_json_params();

    if (!get_post($pool_id) || get_post_type($pool_id) !== 'piscine') {
        return new WP_REST_Response(['success' => false, 'message' => 'Piscine non trouvée'], 404);
    }

    if (!empty($params['nom_piscine'])) {
        wp_update_post(['ID' => $pool_id, 'post_title' => sanitize_text_field($params['nom_piscine'])]);
    }

    $fields_to_update = ['type_piscine', 'longueur', 'largeur', 'profondeur', 'type_filtration', 'type_traitement', 'equipements', 'produits'];
    foreach ($fields_to_update as $field) {
        if (isset($params[$field])) {
            update_field($field, $params[$field], $pool_id);
        }
    }

    if (isset($params['longueur']) || isset($params['largeur']) || isset($params['profondeur'])) {
        $volume = floatval(get_field('longueur', $pool_id)) * floatval(get_field('largeur', $pool_id)) * floatval(get_field('profondeur', $pool_id));
        update_field('volume', $volume, $pool_id);
    }

    if (!empty($params['adresse']) && !empty($params['code_postal']) && !empty($params['ville']) && !empty($params['pays'])) {
        $adresse_complete = sanitize_text_field($params['adresse']) . ', ' .
                            sanitize_text_field($params['code_postal']) . ' ' .
                            sanitize_text_field($params['ville']) . ', ' .
                            sanitize_text_field($params['pays']);
        update_field('adresse_textuelle', $adresse_complete, $pool_id);
        $coordinates = sunny_geocode_address($adresse_complete);
        if ($coordinates) {
            update_field('latitude',  $coordinates['lat'], $pool_id);
            update_field('longitude', $coordinates['lon'], $pool_id);
        }
    }

    // ── Gestion de l'image de la piscine (base64) ───────────────
    if (!empty($params['image_base64'])) {
        $photo_id = sunny_pool_upload_base64_image($params['image_base64'], $pool_id);
        if ($photo_id && !is_wp_error($photo_id)) {
            // Ajouter à la galerie existante ou créer nouvelle
            $existing_photos = get_field('photos_de_la_piscine', $pool_id) ?: [];
            if (!is_array($existing_photos)) $existing_photos = [];
            $existing_photos[] = $photo_id;
            update_field('photos_de_la_piscine', $existing_photos, $pool_id);
        }
    }

    return new WP_REST_Response([
        'success' => true,
        'message' => 'Piscine mise à jour avec succès',
        'data'    => ['id' => $pool_id, 'pool' => sunny_pool_format_pool_data($pool_id)]
    ], 200);
}

/**
 * Supprime une piscine
 */
function sunny_pool_api_delete_pool($request) {
    $pool_id = $request->get_param('id');

    if (!get_post($pool_id) || get_post_type($pool_id) !== 'piscine') {
        return new WP_REST_Response(['success' => false, 'message' => 'Piscine non trouvée'], 404);
    }

    $result = wp_delete_post($pool_id, true);

    if (!$result) {
        return new WP_REST_Response(['success' => false, 'message' => 'Erreur lors de la suppression'], 500);
    }

    return new WP_REST_Response(['success' => true, 'message' => 'Piscine supprimée avec succès', 'data' => ['id' => $pool_id]], 200);
}

// ========== FONCTIONS UTILITAIRES ==========

/**
 * Retourne le libellé lisible d'un produit à partir de sa clé technique
 */
function sunny_pool_get_product_label($key) {
    $map = [
        'chlore_liquide'    => 'Chlore liquide',
        'chlore_galets'     => 'Chlore en galets',
        'chlore_choc'       => 'Chlore choc',
        'ph_plus'           => 'pH+',
        'ph_moins'          => 'pH-',
        'brome_galets'      => 'Brome en galets',
        'brome_liquide'     => 'Brome liquide',
        'sel_electrolyseur' => 'Sel pour électrolyseur',
        'stabilisant'       => 'Stabilisant',
        'acide'             => 'Acide muriatique',
        'produit_nettoyant' => 'Produit nettoyant',
        'algicide'          => 'Algicide',
        'clarifiant'        => 'Clarifiant',
        'oxygene_actif_prod'=> 'Oxygène actif',
    ];
    return $map[$key] ?? $key;
}

/**
 * Formate les données d'une piscine pour l'API
 */
function sunny_pool_format_pool_data($pool_id) {
    $pool = get_post($pool_id);
    if (!$pool) return null;

    $photos     = get_field('photos_de_la_piscine', $pool_id);
    $photos_urls = [];
    if ($photos && is_array($photos)) {
        foreach ($photos as $photo) {
            $image_id = is_array($photo) && isset($photo['ID']) ? $photo['ID'] : (is_numeric($photo) ? $photo : null);
            if ($image_id) {
                $photos_urls[] = [
                    'id'        => $image_id,
                    'url'       => wp_get_attachment_image_url($image_id, 'medium'),
                    'thumbnail' => wp_get_attachment_image_url($image_id, 'thumbnail'),
                    'full'      => wp_get_attachment_image_url($image_id, 'full')
                ];
            }
        }
    }

    // Lecture produits depuis meta JSON (_sunny_produits) avec fallback ACF
    $produits_json = get_post_meta($pool_id, '_sunny_produits', true);
    $produits_acf  = get_field('produits', $pool_id);
    $produits      = [];

    if (!empty($produits_json)) {
        $decoded = json_decode($produits_json, true);
        if (is_array($decoded)) $produits = $decoded;
    } elseif (!empty($produits_acf) && is_array($produits_acf)) {
        $produits = $produits_acf;
    }

    $produits_formatted = [];
    if ($produits && is_array($produits)) {
        foreach ($produits as $produit) {
            $nom = $produit['nom_produit'] ?? ($produit['nom'] ?? '');

            // Formater les URLs des photos
            $photo_face_url   = '';
            $photo_notice_url = '';
            if (!empty($produit['photo_face'])) {
                $photo_face_url = is_numeric($produit['photo_face'])
                    ? wp_get_attachment_image_url($produit['photo_face'], 'medium')
                    : $produit['photo_face'];
            }
            if (!empty($produit['photo_notice'])) {
                $photo_notice_url = is_numeric($produit['photo_notice'])
                    ? wp_get_attachment_image_url($produit['photo_notice'], 'medium')
                    : $produit['photo_notice'];
            }

            $produits_formatted[] = [
                'id'             => $produit['id'] ?? uniqid('prod_'),
                'nom_technique'  => $nom,
                'nom_affichage'  => sunny_pool_get_product_label($nom),
                'categorie'      => $produit['categorie'] ?? '',
                'marque'         => $produit['marque'] ?? '',
                'quantite'       => $produit['quantite'] ?? ($produit['quantity'] ?? 0),
                'unite'          => $produit['unite'] ?? ($produit['unit'] ?? ''),
                'date_ajout'     => $produit['date_ajout'] ?? '',
                'commentaire'    => $produit['commentaire'] ?? '',
                'photo_face'     => $photo_face_url,
                'photo_notice'   => $photo_notice_url
            ];
        }
    }

    return [
        'id'                => $pool_id,
        'titre'             => $pool->post_title,
        'date_creation'     => $pool->post_date,
        'date_modification' => $pool->post_modified,
        'caracteristiques'  => [
            'type'        => get_field('type_piscine',    $pool_id),
            'longueur'    => doubleval(get_field('longueur',        $pool_id)),
            'largeur'     => doubleval(get_field('largeur',         $pool_id)),
            'profondeur'  => doubleval(get_field('profondeur',      $pool_id)),
            'volume'      => get_field('volume',          $pool_id),
            'filtration'  => get_field('type_filtration', $pool_id),
            'traitement'  => get_field('type_traitement', $pool_id),
            'equipements' => get_field('equipements',     $pool_id) ?: []
        ],
        'localisation'      => [
            'adresse'   => get_field('adresse_textuelle', $pool_id),
            'latitude'  => doubleval(get_field('latitude',          $pool_id)),
            'longitude' => doubleval(get_field('longitude',         $pool_id))
        ],
        'photos'            => $photos_urls,
        'produits'          => $produits_formatted,
        'liens'             => [
            'detail'   => get_permalink($pool_id),
            'api'      => rest_url('sunny-pool/v1/pool/' . $pool_id),
            'weather'  => rest_url('sunny-pool/v1/pool/' . $pool_id . '/weather'),
            'products' => rest_url('sunny-pool/v1/pool/' . $pool_id . '/products')
        ]
    ];
}