// ══════════════════════════════════════════════════════
// NŒUD 3 — Préparer Prompt (VERSION RENFORCÉE – Analyse obligatoire des notices)
//
// CHANGEMENTS v3 :
// ✅ Instructions prioritaires explicites avant chaque image produit
// ✅ Notice analysée AVANT la face (plus importante pour dosage)
// ✅ Message d'alerte renforcé dans user_content si photos produits présentes
// ✅ Base64 nettoyé plus agressivement (tous les espaces/retours)
// ✅ Format data:image/jpeg;base64, imposé uniformément
// ══════════════════════════════════════════════════════

const wb = $("Webhook").first().json.body || {};
const pool = wb.pool || {};
const analyse = wb.analyse || {};

const imageType = wb.image_type || 'general';
const dataOptions = wb.data_options || {};

const optMeteo = dataOptions.meteo !== false; // default: true
const optHistorique = dataOptions.historique !== false; // default: true
const optProduits = dataOptions.produits !== false; // default: true
const optAlertes = dataOptions.alertes !== false; // default: true
const optPlanning = !!dataOptions.planning; // default: false

// ── Météo ──────────────────────────────────────────────
const meteoData = $input.first().json || {};
const daily = meteoData.daily || {};

// Utiliser UNIQUEMENT les données du jour actuel (index 0), pas le max de toute la période
const maxT = Array.isArray(daily.temperature_2m_max) && daily.temperature_2m_max.length
  ? Math.round(daily.temperature_2m_max[0]) : 25;
const minT = Array.isArray(daily.temperature_2m_min) && daily.temperature_2m_min.length
  ? Math.round(daily.temperature_2m_min[0]) : 15;
// Pluie du jour actuel uniquement
const pluie = Array.isArray(daily.precipitation_sum) && daily.precipitation_sum.length
  ? daily.precipitation_sum[0] : 0;

const filtrationReco = maxT > 33 ? '24h/24' : (Math.round(maxT / 2) + 2) + 'h/jour';

// ── Labels lisibles (champs ACF WordPress) ─────────────
const typePool = ({ enterree: 'enterrée', hors_sol: 'hors-sol' }[pool.type] || pool.type || '?');
const filtrePool = ({ sable: 'sable', cartouche: 'cartouche', electrolyse: 'électrolyse sel', verre: 'verre' }[pool.filtre] || pool.filtre || '?');
const traitPool = ({ chlore: 'chlore', brome: 'brome', electrolyse: 'électrolyse sel', uv: 'UV', oxygene_actif: 'oxygène actif' }[pool.traitement] || pool.traitement || '?');

// ── Alertes eau automatiques ────────────────────────────
const alertes = [];
if (optAlertes) {
  if (analyse.ph !== null && analyse.ph !== undefined) {
    if (analyse.ph < 7.0) alertes.push({ urgence: 'haute', msg: 'pH trop bas (' + analyse.ph + ') → eau agressive, ajouter pH+' });
    if (analyse.ph > 7.6) alertes.push({ urgence: 'haute', msg: 'pH trop élevé (' + analyse.ph + ') → chlore inefficace, corriger pH en premier' });
  }
  if (analyse.chlore !== null && analyse.chlore !== undefined && analyse.chlore < 0.5)
    alertes.push({ urgence: 'haute', msg: 'Chlore très bas (' + analyse.chlore + ' mg/L) → désinfection insuffisante' });
  if (analyse.tac !== null && analyse.tac !== undefined && analyse.tac < 80)
    alertes.push({ urgence: 'moyenne', msg: 'TAC bas (' + analyse.tac + ' mg/L) → pH instable, corriger en premier' });
  if (analyse.stabilisant !== null && analyse.stabilisant !== undefined && analyse.stabilisant > 60)
    alertes.push({ urgence: 'moyenne', msg: 'Stabilisant élevé (' + analyse.stabilisant + ' mg/L) → dilution + stop chlore stabilisé' });

  // Alertes météo uniquement si la météo est transmise
  if (optMeteo) {
    if (maxT > 33) alertes.push({ urgence: 'haute', msg: 'Canicule ' + maxT + '°C → filtration 24h/24' });
    else if (maxT > 28) alertes.push({ urgence: 'haute', msg: 'Fortes chaleurs ' + maxT + '°C → augmenter filtration' });
    if (minT < 3) alertes.push({ urgence: 'haute', msg: 'Risque gel ' + minT + '°C → filtration nuit obligatoire' });
    if (parseFloat(pluie) > 20) alertes.push({ urgence: 'haute', msg: 'Fortes pluies ' + pluie + 'mm → surveiller équilibre' });
  }
}

// ── Planning ──────────────────────────────────────────
const planning = {
  filtration_journaliere: filtrationReco,
  hebdomadaire: ['Vérifier pH et chlore (2x/sem)', 'Vider skimmers (2x/sem)', 'Robot piscine (3x/sem)', 'Brosser parois et ligne eau'],
  mensuel: ['Laver filtre (backwash ou cartouche)', 'Vérifier TAC et stabilisant', 'Nettoyer intérieur skimmers']
};

// ── Historique de conversation (envoyé par WordPress) ──
let conversationHistory = [];
if (optHistorique && wb.history) {
  try {
    if (Array.isArray(wb.history)) {
      conversationHistory = wb.history.slice(-20);
    } else {
      const parsed = JSON.parse(wb.history);
      if (Array.isArray(parsed)) {
        conversationHistory = parsed.slice(-20);
      }
    }
  } catch (e) { conversationHistory = []; }
}


// ══════════════════════════════════════════════════════
// DOCUMENTATION PISCINE INTÉGRÉE (remplace googleDocsTool)
// Contenu des 6 PDFs de référence Sunny
// ══════════════════════════════════════════════════════
const DOCUMENTATION_PISCINE = `=== PDF 1   FONDAMENTAUX DE L EAU DE PISCINE ===
r📄 PDF 1 — FONDAMENTAUX DE L’EAU DE PISCINE (VERSION FINALE)
🎯 Objectif
Comprendre les paramètres essentiels de l’eau afin de garantir :
une eau saine
un confort de baignade optimal
une efficacité maximale des traitements
1. LE pH (POTENTIEL HYDROGÈNE)
Définition
Le pH mesure l’acidité ou la basicité de l’eau.
Échelle de 0 à 14 :
< 7 : acide
= 7 : neutre
7 : basique
Valeur idéale
👉 Entre 7.0 et 7.4
👉 Référence physiologique importante :
Le pH des larmes humaines est d’environ 7,2
➡️ Conclusion :
Maintenir le pH autour de 7,2 permet :
un confort optimal (pas d’irritation)
une eau “naturelle” pour le corps
Rôle
Optimise l’efficacité du chlore
Garantit le confort des baigneurs
Protège les équipements
pH trop bas (< 7.0)
Conséquences
Eau agressive
Irritations peau et yeux
Corrosion des équipements
Consommation rapide du chlore
Correction
👉 Ajouter du pH+
pH trop haut (> 7.4)
Conséquences
Chlore inefficace (point critique)
Eau trouble
Dépôts calcaires
Développement d’algues
Correction
👉 Ajouter du pH-
2. LE TAC (ALCALINITÉ)
Définition
Le TAC mesure la capacité de l’eau à stabiliser le pH.
Valeur idéale
👉 Entre 80 et 120 mg/L
Rôle
Stabilise le pH
Évite les variations brutales
TAC trop bas
Conséquences
pH instable
corrections inefficaces
Correction
👉 Ajouter du TAC+ (bicarbonate)
TAC trop élevé
Conséquences
pH difficile à corriger
eau trouble possible
Correction
👉 dilution (ajout d’eau neuve)
3. LE TH (DURETÉ / CALCAIRE)
Définition
Le TH mesure la quantité de calcaire dans l’eau.
Valeur idéale
👉 Entre 10 et 25 °f
TH trop élevé
Conséquences
dépôts calcaires
entartrage équipements
taches blanches
eau trouble
TH trop bas
Conséquences
eau agressive
corrosion
Correction
👉 anti-calcaire + dilution si nécessaire
4. MÉTAUX DANS L’EAU (FER / CUIVRE)
Définition
Présence de métaux dissous (souvent eau de remplissage).
Effets visibles
Fer
taches marron / rouille
points localisés
Cuivre
coloration verdâtre
taches sombres
Risque
👉 Réaction avec :
chlore
pH élevé
➡️ apparition de taches souvent difficiles à enlever
Traitement préventif (OBLIGATOIRE)
👉 À la mise en eau :
ajouter un séquestrant métaux
👉 référence terrain efficace :
Piscimar
Objectif
neutraliser les métaux
éviter les taches
stabiliser l’eau
5. LA TEMPÉRATURE DE L’EAU
Impact
Influence directe sur :
consommation de chlore
développement des algues
Règle standard
👉 Temps de filtration = Température / 2
Cas critique
👉 Dès 25–26°C :
risque d’algues élevé
chlore instable
En période de canicule
👉 Recommandation professionnelle :
filtration 24h/24
surveillance quotidienne
Actions
augmenter filtration
ajuster traitement
anticiper dérives
6. LE DÉSINFECTANT (CHLORE / BROME / SEL)
Rôle
éliminer bactéries, virus, algues
maintenir une eau saine
Types principaux
Chlore
économique
efficace
sensible au pH
Brome
stable à haute température
moins irritant
Sel (électrolyse)
production automatique de chlore
confort élevé
coût plus important
Alternative professionnelle (IMPORTANT)
👉 Automatisation à coût réduit :
pompe doseuse chlore liquide
régulation pH automatique
➡️ Avantages :
moins cher que l’électrolyse
très précis
idéal pour maintenance pro
Valeurs idéales
Chlore : 1 à 3 mg/L
Brome : 2 à 4 mg/L
Problème fréquent
Chlore inefficace
👉 causes :
pH trop élevé
température élevée
filtration insuffisante
⚠️ RÈGLES FONDAMENTALES
TAC → pH → désinfectant (ordre obligatoire)
toujours filtrer après traitement
ne jamais mélanger produits
traiter les métaux à la mise en eau
🧠 LOGIQUE GLOBALE
👉 Une eau équilibrée =
stabilité (TAC)
efficacité (pH)
désinfection (chlore)
pureté (absence de métaux)
✅ CONCLUSION
Une bonne gestion de l’eau repose sur :
compréhension des paramètres
anticipation des dérives
traitement préventif
👉 90% des problèmes viennent d’un mauvais équilibre de base

=== PDF 2   LOGIQUE D ÉQUILIBRE ET DE CORRECTION DE L EAU ===
📄 PDF 2 — LOGIQUE D’ÉQUILIBRE ET DE CORRECTION DE L’EAU (VERSION EXPERT)
🎯 Objectif
Permettre à l’IA de :
analyser une eau
diagnostiquer un problème
appliquer une logique de correction fiable
🧠 PRINCIPE FONDAMENTAL
👉 Une piscine se corrige dans un ordre précis :
🔁 ORDRE DE CORRECTION
TAC
pH
Désinfectant
👉 Si cet ordre n’est pas respecté :
inefficacité
surdosage
problèmes récurrents
1. ANALYSE DE BASE
Paramètres à mesurer
TAC
pH
chlore
température
stabilisant (important)
2. CORRECTION DU TAC
TAC trop bas (< 80 mg/L)
👉 problème :
pH instable
👉 action :
ajouter TAC+
TAC trop haut (> 120 mg/L)
👉 problème :
pH difficile à corriger
👉 actions possibles :
dilution (ajout eau neuve)
utilisation de TAC- (si disponible / usage pro)
⚠️ RÈGLE
👉 TAC toujours corrigé AVANT pH
3. CORRECTION DU pH
pH trop bas (< 7.0)
👉 action :
ajouter pH+
pH trop haut (> 7.4)
👉 problème critique :
chlore inefficace
👉 action :
ajouter pH-
⚠️ RÈGLE CRITIQUE
👉 Tant que le pH n’est pas bon →
 👉 le chlore est inefficace
4. DÉSINFECTANT & STABILISANT
Ajustement chlore
< 1 mg/L → chlore choc ou entretien
⚠️ STABILISANT (TRÈS IMPORTANT)
👉 À intégrer systématiquement :
Rôle
protège le chlore contre les UV
évite sa destruction rapide
Cas critiques
Piscine au sel
👉 stabilisant indispensable
Chlore liquide
👉 stabilisant obligatoire (sinon perte immédiate)
Problème fréquent
👉 trop de stabilisant :
chlore inefficace
eau saturée
👉 solution :
dilution partielle
5. IMPACT TEMPÉRATURE & FILTRATION
Règle améliorée terrain
👉 Temps de filtration =
 Température / 2 + 2 heures
Priorité absolue
👉 filtrer :
en journée
pendant les baignades
Forte chaleur (> 25–26°C)
👉 actions :
filtration augmentée
en canicule → 24h/24
⚠️ RÈGLE CLÉ
👉 La filtration fait 80% du travail
6. LOGIQUE FILTRATION
Si problème eau :
👉 toujours vérifier :
état du filtre
durée de filtration
Actions correctives
lavage filtre (backwash)
nettoyage cartouche
augmentation durée
7. FLOCCULANT / CLARIFIANT (IMPORTANT)
⚠️ RÈGLE
👉 floculant NON compatible avec tous les filtres
Filtre à sable
👉 OK floculant
Filtre à cartouche
👉 PAS de floculant
👉 utiliser :
clarifiant en pastille dans le panier pompe
➡️ résultat :
eau claire en 24–48h
8. CAS CONCRETS (LOGIQUE IA)
CAS 1 — EAU VERTE (PROTOCOLE PRO)
Causes
chlore insuffisant
pH élevé
filtration insuffisante
Plan d’action complet
filtration non-stop (24h/24)
nettoyage filtre :
backwash ou cartouche
3 à 4 fois par jour
brossage :
parois
fond
marches
plusieurs fois / jour
correction :
TAC
pH
traitement :
chlore choc (hypochlorite non stabilisé recommandé)
anti-algues
finition :
clarifiant ou floculant selon filtre
aspiration des dépôts
CAS 2 — EAU TROUBLE
Causes
filtration insuffisante
filtre encrassé
déséquilibre chimique
Plan d’action
nettoyage filtre
ajuster TAC
ajuster pH
clarifiant / floculant selon filtre
CAS 3 — CHLORE INEFFICACE
Cause principale
👉 pH trop élevé
 👉 ou stabilisant mal géré
Plan d’action
corriger pH
vérifier stabilisant
relancer traitement
9. CHLORE CHOC (POINT IMPORTANT)
Type recommandé
👉 hypochlorite de calcium (non stabilisé)
Avantages
action rapide
ne surcharge pas en stabilisant
idéal en traitement choc
À éviter
👉 accumulation de stabilisant avec chlore stabilisé
10. ERREURS CLASSIQUES
❌ traiter sans analyser
 ❌ ignorer filtration
 ❌ utiliser floculant avec cartouche
 ❌ oublier stabilisant
 ❌ surdoser chlore stabilisé
🧠 LOGIQUE IA (SUNNYPOOL)
Exemple
Si :
pH = 7.8
chlore = bas
👉 IA doit dire :
❌ ne pas ajouter chlore
 ✅ corriger pH
Objectif
👉 guider intelligemment
 👉 éviter erreurs client
 👉 réduire SAV
✅ CONCLUSION
👉 Une eau propre dépend :
de la logique
de la filtration
de la rigueur
👉 L’ordre + la méthode = 90% du résultat

=== PDF 3   ENTRETIEN COMPLET DE LA PISCINE ===
📄 PDF 3 — ENTRETIEN COMPLET DE LA PISCINE (VERSION EXPERT TERRAIN)
🎯 Objectif
Permettre à l’IA de :
guider un client au quotidien
éviter les erreurs coûteuses
structurer un entretien professionnel fiable
🧠 PRINCIPE FONDAMENTAL
👉 Une piscine bien entretenue =
👉 moins de produits + moins de problèmes
👉 Anticiper vaut toujours mieux que corriger
1. ENTRETIEN QUOTIDIEN
Actions
retirer les débris
vérifier visuellement l’eau
contrôler température
2. ENTRETIEN HEBDOMADAIRE
Nettoyage
robot piscine
brossage parois / fond / marches
nettoyage ligne d’eau
⚠️ ROBOT PISCINE (IMPORTANT)
👉 Plus le robot fonctionne → mieux c’est
➡️ Il remplace :
le brossage manuel
l’aspiration
👉 utilisation recommandée :
plusieurs fois / semaine
voire quotidien en forte saison
Technique
vider skimmers
nettoyer panier pompe
nettoyer intérieur des skimmers (souvent négligé)
Analyse
pH
chlore
TAC
3. ENTRETIEN MENSUEL
Actions
nettoyage filtre approfondi
contrôle TAC / TH
vérification équipements
4. FILTRATION (PILIER N°1)
Règle
👉 Temps = Température / 2 + 2 heures
Priorité
👉 filtrer :
en journée
pendant les baignades
Forte chaleur
👉 >25–26°C :
augmenter filtration
canicule → 24h/24
⚠️ RÈGLE CLÉ
👉 La filtration = 80% du résultat
Choix du filtre (terrain)
Filtre à cartouche (recommandé)
👉 avantages :
pas de perte d’eau
pas de perte de sel / stabilisant / produits
plus écologique
Filtre à sable
👉 inconvénients :
backwash = perte :
eau
sel
stabilisant
chlore
👉 particulièrement pénalisant en piscine au sel
⚠️ NETTOYAGE CARTOUCHE
👉 ne jamais utiliser un karcher trop proche
➡️ risque :
destruction de la cartouche
👉 utiliser :
jet d’eau classique
5. NETTOYAGE DU BASSIN
Actions
brossage complet
nettoyage ligne d’eau
aspiration
Objectif
👉 éliminer :
biofilm
algues
dépôts
6. SKIMMERS & PRÉFILTRE
Actions
vider paniers
nettoyer préfiltre
nettoyer intérieur skimmers
Pourquoi
👉 accumulation :
algues
impuretés
7. TRAITEMENT DE L’EAU (BONNES PRATIQUES)
⚠️ RÈGLE ABSOLUE
👉 jamais de produit directement concentré dans le bassin
Méthode correcte
prendre un seau
diluer le produit
verser dans le bassin
👉 répartir dans zones profondes
⚠️ À ÉVITER
marches
zones peu profondes
⚠️ INTERDICTION
👉 ne jamais mettre de chlore dans les skimmers
➡️ conséquences :
détérioration skimmer
destruction tuyauterie
👉 utiliser :
diffuseur flottant
8. GESTION DU SEL
Règle
👉 ne jamais ajouter le sel sans préparation
Ordre
équilibrer eau
ajuster stabilisant
ajouter sel
9. VIDANGE DU BASSIN (DANGER)
⚠️ RÈGLE N°1
👉 ne jamais vider un bassin sans précaution
Risque
👉 poussée d’Archimède
➡️ le bassin peut :
se fissurer
remonter
Procédure obligatoire
vérifier présence puits de décompression
installer pompe vide cave
maintenir niveau d’eau extérieur maîtrisé
10. ENTRETIEN EN CAS DE PROBLÈME
Eau verte (PROTOCOLE COMPLET)
👉 actions :
filtration 24h/24
nettoyage filtre 3–4 fois / jour
brossage intensif
ajustement TAC
ajustement pH
chlore choc (non stabilisé recommandé)
anti-algues
clarifiant / floculant
aspiration des dépôts
Eau trouble
nettoyage filtre
ajustement TAC
ajustement pH
clarifiant / floculant
11. HIVERNAGE (RECOMMANDATION PRO)
Préconisation
👉 privilégier hivernage actif
Avantages
moins de produits
eau propre toute l’année
remise en service facile
12. REMISE EN SERVICE
Étapes
nettoyage bassin
remise filtration
analyse eau
ajustement TAC / pH
chlore choc obligatoire
Objectif
👉 éliminer bactéries et algues hivernales
13. ERREURS CLASSIQUES
❌ négliger filtration
❌ mettre produits dans skimmer
❌ utiliser karcher sur cartouche
❌ vider piscine sans précaution
❌ traiter sans dilution
🧠 LOGIQUE IA
👉 entretien =
régularité
méthode
anticipation
✅ CONCLUSION
👉 Une piscine bien entretenue :
coûte moins cher
reste propre
évite le SAV
👉 La discipline fait toute la différence

=== PDF 4   DIAGNOSTIC   RÉSOLUTION DES PROBLÈMES PISCINE ===
📄 PDF 4 — DIAGNOSTIC & RÉSOLUTION DES PROBLÈMES PISCINE (VERSION IA + TERRAIN)
🎯 Objectif
Permettre à l’IA de :
analyser un problème avec précision
croiser visuel + données + contexte
proposer une solution fiable
🧠 MÉTHODE DE DIAGNOSTIC (STRUCTURE IA)
Étape 1 — Observation visuelle
couleur de l’eau
transparence
présence de dépôts
taches
Étape 2 — Analyse technique
TAC
pH
chlore
température
Étape 3 — Contexte (TRÈS IMPORTANT)
👉 L’IA doit obligatoirement demander :
depuis quand le problème existe
traitement déjà effectué
type de filtration (sable / cartouche)
fréquence d’entretien
météo récente
⚠️ RÈGLE IA CRITIQUE
👉 Une photo seule ne suffit pas
👉 Il faut :
photo + description du client
➡️ sinon :
diagnostic imprécis
mauvaise réponse
1. EAU VERTE (CAS MAJEUR)
Diagnostic
algues
chlore insuffisant
pH trop haut
filtration insuffisante
Plan d’action (PROTOCOLE PRO)
filtration 24h/24
nettoyage filtre :
3 à 4 fois / jour
brossage intensif :
parois
fond
marches
correction :
TAC
pH
traitement :
chlore choc non stabilisé
anti-algues
finition :
clarifiant / floculant
aspiration
2. EAU TROUBLE / LAITEUSE
Diagnostic
filtration insuffisante
filtre saturé
produit inadapté
⚠️ CAS SPÉCIFIQUE FILTRE CARTOUCHE
👉 très fréquent :
utilisation mauvais chlore ou stabilisant
surcharge produit
➡️ résultat :
eau laiteuse
dépôt blanc
cartouche qui “coule blanc”
Plan d’action
filtration 24h/24
nettoyage cartouche très fréquent
ajuster TAC
ajuster pH
utiliser clarifiant (pas floculant)
3. TACHES DANS LE BASSIN
Diagnostic
Taches marron
👉 fer
Taches blanches
👉 calcaire
Taches noires
👉 algues incrustées
Plan d’action
identifier cause
séquestrant métaux si besoin
ajustement pH
brossage
4. CHLORE INEFFICACE
Diagnostic
👉 causes principales :
pH trop élevé
stabilisant trop élevé
température élevée
Plan d’action
corriger pH
vérifier stabilisant
ajuster chlore
5. EAU QUI TOURNE RAPIDEMENT
Diagnostic
manque filtration
chaleur
déséquilibre
Plan d’action
augmenter filtration
surveillance quotidienne
traitement préventif
6. ALGUES TENACES
Diagnostic
zones mal brassées
manque désinfection
Plan d’action
brossage intensif
traitement choc
nettoyage filtre
7. FILTRE INEFFICACE
Diagnostic
filtre saturé
cartouche HS
sable encrassé
Plan d’action
nettoyage
remplacement si nécessaire
8. CAS CRITIQUE
Symptômes
eau opaque
forte odeur
fond invisible
Plan d’action
👉 protocole eau verte complet
⚠️ ERREURS CRITIQUES À ÉVITER
❌ analyser uniquement avec une photo
❌ ignorer type de filtration
❌ utiliser floculant avec cartouche
❌ surcharger en stabilisant
❌ négliger nettoyage filtre
🧠 LOGIQUE IA (INTELLIGENTE)
L’IA doit systématiquement :
demander contexte
vérifier filtration
vérifier pH
analyser cohérence
Exemple
Photo eau trouble + client dit :
“j’ai mis beaucoup de chlore”
👉 IA doit comprendre :
surdosage
filtration insuffisante
possible saturation cartouche
✅ CONCLUSION
👉 Diagnostic fiable =
visuel
données
contexte
👉 Sans contexte → IA inefficace

=== PDF 5   MISE EN SERVICE PISCINE ===
📄 PDF 5 — MISE EN SERVICE PISCINE (VERSION PRO – FINALE)
🎯 Objectif
Permettre à l’IA de :
guider un démarrage parfait
rattraper une eau neuve sale
éviter 90% des erreurs dès le début
🧠 PRINCIPE FONDAMENTAL
👉 Une mauvaise mise en service =
eau instable
algues rapides
SAV immédiat
👉 Une bonne mise en service =
eau propre en 24–72h
stabilité durable
⚠️ CAS SPÉCIFIQUE — REMPLISSAGE AVEC EAU DE FORAGE
Peut-on remplir avec de l’eau de forage ?
👉 OUI — MAIS traitement obligatoire
Problèmes fréquents
forte présence de métaux (fer)
eau chargée
risque d’algues immédiat
Traitement obligatoire dès remplissage
👉 à faire immédiatement :
chlore choc
anti-algues (obligatoire)
séquestrant métaux
anti-calcaire
⚠️ Sans traitement
👉 apparition rapide :
eau verte
taches rouille
eau trouble
1. ÉTAPE 1 — MISE EN ROUTE
démarrer filtration immédiatement
vérifier circulation
👉 jamais d’eau stagnante
2. ÉTAPE 2 — ANALYSE
TAC
pH
chlore
température
3. ÉTAPE 3 — TRAITEMENT MÉTAUX
👉 ajouter :
séquestrant métaux
👉 référence terrain :
Piscimar
4. ÉTAPE 4 — ÉQUILIBRAGE
👉 ordre :
TAC
pH
5. ÉTAPE 5 — STABILISANT
👉 obligatoire :
chlore liquide
électrolyse au sel
6. ÉTAPE 6 — CHLORE CHOC (CRITIQUE)
Règle
👉 chlore choc obligatoire au démarrage
Type recommandé
👉 hypochlorite de calcium (non stabilisé)
Pourquoi
action immédiate
détruit bactéries et algues
ne surcharge pas en stabilisant
⚠️ IMPORTANT
👉 même pour piscine au sel :
➡️ chlore choc obligatoire au démarrage
7. ÉTAPE 7 — FILTRATION INTENSIVE
24h/24
minimum 48h
8. ÉTAPE 8 — NETTOYAGE
brossage complet
robot
aspiration
9. ÉTAPE 9 — CLARIFICATION
filtre sable → floculant
filtre cartouche → clarifiant
10. ÉTAPE 10 — AJOUT DU SEL (SI CONCERNÉ)
👉 uniquement après :
eau équilibrée
stabilisant ajouté
11. CONTRÔLE FINAL
eau claire
pH stable
chlore actif
⚠️ ERREURS CRITIQUES
❌ oublier traitement eau de forage
❌ ne pas faire chlore choc
❌ ajouter sel trop tôt
❌ ne pas traiter métaux
❌ négliger filtration
🧠 LOGIQUE IA
Client dit :
“j’ai rempli avec eau de forage”
👉 IA doit répondre :
traitement immédiat obligatoire
protocole complet
insister anti-algues + métaux
✅ CONCLUSION
👉 Une mise en service réussie repose sur :
anticipation
traitement complet dès le départ
filtration intensive
👉 Les premières 48h conditionnent tout le reste
🔥 RÉCAP EXPRESS
👉 ordre :
filtration
métaux
TAC
pH
stabilisant
chlore choc
nettoyage

=== PDF 6   HIVERNAGE   ENTRETIEN DES SYSTÈMES AUTOMATISÉS ===
📄 PDF 6 — HIVERNAGE & ENTRETIEN DES SYSTÈMES AUTOMATISÉS (VERSION EXPERT FINALE)
🎯 Objectif
Hiverner correctement (actif / passif)
Protéger les équipements (PAC, filtration, sondes)
Garantir un redémarrage simple et sans casse
🧠 PRINCIPE GLOBAL
👉 Hivernage maîtrisé =
eau stable
matériel préservé
zéro mauvaise surprise
1. HIVERNAGE ACTIF (RECOMMANDÉ)
Principe
👉 Fonctionnement ralenti, eau en mouvement
Mise en place
nettoyage complet
équilibrage :
TAC
pH
traitement :
chlore choc
produit hivernage
Filtration (RÈGLES TERRAIN)
👉 Base :
2 à 4h / jour minimum
👉 Priorité absolue : périodes de gel
➡️ programmer filtration :
la nuit
tôt le matin
⚠️ Cas de grand froid
👉 si doute (gel prolongé) :
➡️ filtration 24h/24
Pourquoi
eau en mouvement
évite gel dans les canalisations
évite casse matériel
Circulation complète (IMPORTANT)
👉 ouvrir toutes les lignes :
skimmers
prise balai
bonde de fond
➡️ objectif :
circulation partout
aucune zone stagnante
2. HIVERNAGE PASSIF
Principe
👉 arrêt complet
Étapes
nettoyage
équilibrage
chlore choc
produit hivernage
Actions
abaisser niveau eau
purger canalisations
installer gizmos
arrêter filtration
Inconvénients
eau se dégrade
remise en service plus lourde
3. HIVERNAGE DE LA PAC
Procédure
couper alimentation
fermer by-pass
ouvrir raccords
vidanger complètement
⚠️ POINT CRITIQUE
👉 revisser les embouts après vidange
Pourquoi
éviter insectes
éviter obstruction
Protection
couvrir sans étouffer
4. ENTRETIEN DES SONDES
Fréquence
toutes les 2 à 4 semaines
Actions
rinçage
nettoyage
contrôle visuel
Problèmes
calcaire
biofilm
dérive
5. CALIBRAGE DES SONDES (POINT CRITIQUE)
⚠️ PRÉREQUIS OBLIGATOIRE
👉 Pour calibrer :
soit une sonde de secours
soit un bouchon de sonde
Pourquoi
👉 sinon :
impossible de faire tourner la filtration
impossible de calibrer correctement
Sonde pH
👉 fréquence :
4 à 6 semaines
Solutions tampon
👉 selon appareil :
pH 4
pH 7
pH 10
⚠️ RÈGLE
👉 toujours vérifier la notice fabricant
Sonde chlore (Redox)
👉 fréquence :
1 fois / mois
Important
👉 Redox = indication
👉 pas valeur exacte
6. ENTRETIEN ÉLECTROLYSEUR
Problème
👉 calcaire
Entretien
nettoyage acide léger
contrôle visuel
Fréquence
1 à 2 fois / saison
7. ERREURS CRITIQUES
❌ ne pas filtrer en période de gel
❌ oublier circulation complète
❌ oublier PAC
❌ ne pas revisser raccords
❌ calibrer sans matériel
❌ ignorer notice fabricant
🧠 LOGIQUE IA
👉 L’IA doit :
détecter risque gel
recommander filtration adaptée
vérifier cohérence sondes
demander matériel calibration
Exemple
Température basse + filtration arrêtée
👉 IA :
alerte gel
recommande filtration continue
✅ CONCLUSION
👉 Hivernage réussi =
eau en mouvement
matériel protégé
automatisation fiable
👉 Le gel est le plus gros risque → toujours anticiper`;

// ── System Prompt ──────────────────────────────────────
// Fonction pour formater une date (YYYY-MM-DD HH:MM:SS) → JJ/MM/AAAA
function formatDateShort(dateStr) {
  if (!dateStr) return '';
  const d = new Date(dateStr);
  if (isNaN(d.getTime())) return dateStr.substring(0, 10);
  return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

// Labels catégories (miroir de single-piscine.php)
const CATS_LABELS = {
  chlore_choc: 'Chlore choc', chlore_lent: 'Chlore lent',
  ph_plus: 'pH+', ph_moins: 'pH-',
  anti_algues: 'Anti-algues', clarifiant: 'Clarifiant',
  floculant: 'Floculant', sequestrant_calcaire: 'Séquestrant calcaire',
  sequestrant_metaux: 'Séquestrant métaux'
};

const produitsBruts = optProduits ? (wb.produits || []) : [];

let produitsTexte = '';
let nbPhotosDisponibles = 0;

if (Array.isArray(produitsBruts) && produitsBruts.length > 0) {
  if (typeof produitsBruts[0] === 'string') {
    // Ancien format simple (chaînes)
    produitsTexte = produitsBruts.map(p => '• ' + p).join('\n');
  } else {
    // Format enrichi (objets) — inclut catégorie, marque, dates, commentaire, photos
    produitsTexte = produitsBruts.map((p, idx) => {
      const num        = idx + 1;
      const catLabel   = CATS_LABELS[p.categorie] || p.categorie || '';
      const nomComplet = [catLabel, p.marque, p.nom_produit].filter(Boolean).join(' / ');
      const qte        = (p.quantite != null && p.quantite !== '') ? `${p.quantite} ${(p.unite || '')}`.trim() : '';
      const dateAjout  = p.date_ajout ? formatDateShort(p.date_ajout) : '';
      const dateMaj    = p.date_mise_a_jour ? formatDateShort(p.date_mise_a_jour) : '';

      const hasFace   = !!(p.photo_face_base64 || p.photo_face_url || p.photo_face);
      const hasNotice = !!(p.photo_notice_base64 || p.photo_notice_url || p.photo_notice);
      const photos    = [hasFace && '📸 photo-face', hasNotice && '📄 notice-dosage'].filter(Boolean);
      if (hasFace || hasNotice) nbPhotosDisponibles++;

      const photosTag  = photos.length ? ` → images: [${photos.join(', ')}]` : '';
      const commentTag = p.commentaire ? ` | Note: ${p.commentaire}` : '';
      const dateTag    = dateAjout ? ` | Ajouté: ${dateAjout}${dateMaj && dateMaj !== dateAjout ? ' (màj ' + dateMaj + ')' : ''}` : '';

      return `  Produit ${num}: ${nomComplet}${qte ? ' — stock: ' + qte : ''}${dateTag}${commentTag}${photosTag}`;
    }).join('\n');
  }
} else {
  produitsTexte = optProduits ? '  (aucun produit enregistré)' : '  (mes produits non transmis)';
}

const photosNote = nbPhotosDisponibles > 0
  ? `\n → ${nbPhotosDisponibles} produit(s) avec photos jointes à ce message (analyse-les pour lire le dosage fabricant, concentration, instructions)` 
  : '';

const alertesContextuelles = alertes.length > 0
  ? '\n\n⚠️ ALERTES ACTIVES :\n' + alertes.map(a => `• [${a.urgence.toUpperCase()}] ${a.msg}`).join('\n')
  : '';

const imageContext = {
  water: "L'utilisateur envoie une photo de son eau ou d'une bandelette → analyse visuelle prioritaire.",
  product: "L'utilisateur envoie une photo d'un produit (face/notice) → concentrer sur la notice/dosage fabricant.",
  pool: "L'utilisateur envoie une photo générale de sa piscine → diagnostic d'ensemble (couleur, dépôts, zones).",
  general: "Photo sans type spécifié → analyse standard."
}[imageType] || "Photo sans type spécifié → analyse standard.";

// ── System Prompt (VERSION FINALE NETTOYÉE) ─
const systemPrompt = `Tu es Sunny 🌞 — un assistant virtuel avec la personnalité d'un technicien piscine sympa, expérimenté, et humain.
Tu es intégré dans l'application SunnyPool et tu accompagnes chaque propriétaire de piscine au quotidien.

🎯 TA PERSONNALITÉ (PRIORITAIRE)
Tu es chaleureux, naturel, direct — comme un pro qu'on croise au bord d'une piscine.
Tu adaptes ton ton au message : court → court, technique → précis, casual → détendu.
Tu n'es PAS un robot technique : tu sais aussi dire "salut", "merci", "bonne journée" sans sur-analyser.

🔄 TA LOGIQUE DE RÉPONSE (EN ORDRE)
1️⃣ Salutation / merci (<5 mots, pas de ?) → 1 phrase humaine, ZÉRO technique
2️⃣ Question piscine / technique → Analyse + 1 à 3 actions claires
3️⃣ Photo piscine/eau → Diagnostic visuel + croisement données
4️⃣ Demande dosage/planning → Calcul précis + adaptation volume
5️⃣ Discussion neutre → Réponse naturelle, recentrage doux si utile
6️⃣ Hors-sujet → Bienveillance + redirection vers piscine

📸 IMAGES & CONTEXTE
- N'analyse l'image QUE si elle est présente dans ce message précis.
- Si message texte seul → réponds sans mentionner l'image.
- Croise toujours visuel + données + météo + historique.
- Si doute → pose 1 question courte.

🧠 MÉMOIRE CONVERSATIONNELLE
${optHistorique ? "Tu as accès aux 20 derniers échanges ([HISTORIQUE]).\nUtilise-les pour éviter les répétitions et suivre l'évolution.\n⚠️ Ne jamais inclure le préfixe [HISTORIQUE] dans tes réponses." : "Tu n'as pas accès à l'historique de conversation."}

💬 TON STYLE — HUMAIN AVANT TOUT
✅ "OK je vois 👍", "Classique ça", "On va régler ça", "Franchement…"
✅ 0-1 emoji max, phrases courtes, langage courant
❌ Ton scolaire, listes systématiques, structures robotiques
🔄 VARIATION OBLIGATOIRE : Change tes ouvertures, longueurs et enchaînements à chaque réponse.

🔴 RÈGLES PRO (NON NÉGOCIABLES)
• Ordre : TAC → pH → Désinfectant
• pH > 7.4 → Chlore inefficace, corriger pH d'abord
• Filtration = 80% du résultat
• Filtre cartouche → Clarifiant uniquement, JAMAIS de floculant
• Produits → Toujours dilués dans un seau, jamais dans les skimmers
• Notice produit lisible → Le dosage fabricant = vérité absolue

📚 DOCUMENTATION_PISCINE (RÉFÉRENCE INTERNE)
${DOCUMENTATION_PISCINE}
→ Tu la connais par cœur, mais tu ne la cites JAMAIS littéralement.

📋 DONNÉES BASSIN (CONTEXTE EN TEMPS RÉEL)
Type : ${typePool} | Volume : ${pool.volume || '?'} m³ | Filtration : ${filtrePool}
Traitement : ${traitPool} | Équipements : ${(pool.equipements || []).join(', ') || 'non renseignés'}
Produits : ${produitsTexte}${photosNote}
Analyse : pH ${analyse.ph ?? '?'} | Chlore ${analyse.chlore ?? '?'} mg/L | TAC ${analyse.tac ?? '?'} mg/L
Météo : ${optMeteo ? ('Max ' + maxT + '°C / Min ' + minT + '°C / Pluie ' + pluie + ' mm | Filtration : ' + filtrationReco) : 'non transmise'}
🖼️ CONTEXTE IMAGE : ${imageContext}
${alertesContextuelles}

🎯 OBJECTIF FINAL
L'utilisateur doit repartir avec l'impression d'avoir parlé à un HUMAIN, pas à un robot.
Réponse adaptée à SON message. Conseils fiables QUAND c'est pertinent.
👉 Tu es d'abord un humain sympa. Ensuite, un expert piscine. Jamais l'inverse.`;

// ══════════════════════════════════════════════════════
// 🔥 CONSTRUCTION DES IMAGES (CONDITIONNELLE)
// ══════════════════════════════════════════════════════
const imageItems = [];
let hasProductImages = false;

if (wb.image_base64) {
  const cleanB64 = (wb.image_base64 || '').replace(/\s/g, '');
  const imageUrl = cleanB64.startsWith('data:') ? cleanB64 : 'data:image/jpeg;base64,' + cleanB64;
  imageItems.push({ type: 'image_url', image_url: { url: imageUrl, detail: 'high' } });
}

function cleanBase64(raw) {
  if (!raw) return null;
  let str = raw.replace(/\s/g, '');
  return str.startsWith('data:') ? str : 'data:image/jpeg;base64,' + str;
}

if (optProduits && Array.isArray(wb.produits)) {
  wb.produits.forEach((p, idx) => {
    if (!p || typeof p !== 'object') return;
    const num = idx + 1;
    const nom = [CATS_LABELS[p.categorie], p.marque, p.nom_produit].filter(Boolean).join(' ') || `Produit ${num}`;
    const stock = p.quantite ? `(stock: ${p.quantite} ${p.unite || ''})` : '';

    const noticeSrc = cleanBase64(p.photo_notice_base64) || (p.photo_notice_url && p.photo_notice_url.startsWith('http') ? p.photo_notice_url : null);
    if (noticeSrc) {
      hasProductImages = true;
      imageItems.push({ type: 'text', text: `⚠️ IMAGE PRODUIT ${num} — NOTICE : "${nom}".\nLis la notice, extrais le dosage exact (g/10m³ ou g/m³).` });
      imageItems.push({ type: 'image_url', image_url: { url: noticeSrc, detail: 'high' } });
    }

    const faceSrc = cleanBase64(p.photo_face_base64) || (p.photo_face_url && p.photo_face_url.startsWith('http') ? p.photo_face_url : null);
    if (faceSrc) {
      hasProductImages = true;
      imageItems.push({ type: 'text', text: `📸 IMAGE PRODUIT ${num} — FACE : "${nom}"${stock}` });
      imageItems.push({ type: 'image_url', image_url: { url: faceSrc, detail: 'high' } });
    }
  });
}

const userText = wb.message || "Voici une photo de mon bassin, qu'en penses-tu ?";
const userContent = imageItems.length > 0
  ? [{ type: 'text', text: hasProductImages ? '🚨 Lis les notices ci-dessous en priorité.\n\n' + userText : userText }, ...imageItems]
  : userText;

const messagesArray = [
  { role: 'system', content: systemPrompt },
  ...conversationHistory,
  { role: 'user', content: userContent }
];

return [{
  json: {
    messages_array: messagesArray,
    system_prompt: systemPrompt,
    user_content: userContent,
    has_image: imageItems.length > 0,
    session_id: wb.session_id || 'default',
    conversation_id: wb.conversation_id || 'default_conv',
    callback_url: wb.callback_url || '',
    alertes,
    planning: optPlanning ? { filtration_journaliere: filtrationReco } : {},
    pool_filtre: filtrePool,
    meta_max_temp: maxT
  }
}];
