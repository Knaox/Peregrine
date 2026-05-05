# Spécification fonctionnelle — Gestionnaire de modpacks Minecraft

> Document destiné à guider une réimplémentation **from scratch** sur une plateforme tierce (panel React/TypeScript + backend Laravel). Toute l'abstraction est volontaire : aucun choix d'implémentation n'est imposé, seules les fonctionnalités, les parcours et les contraintes externes le sont.

---

## 1. Vision produit

Le module ajoute, à un panel d'hébergement de serveurs Minecraft, la capacité pour un joueur de **découvrir, installer, mettre à jour et désinstaller un modpack** sur l'un de ses serveurs sans intervention de l'administrateur, en s'appuyant sur les principales places de marché publiques de modpacks. La fonctionnalité est exposée par serveur : chaque serveur compatible reçoit un onglet dédié dans son interface.

L'objectif est de transformer un serveur Minecraft « vanilla » en un serveur exécutant un modpack choisi parmi des centaines, en quelques clics, avec gestion automatique de la version de Java requise et nettoyage optionnel des fichiers existants.

Pendant l'installation, le serveur entre dans un **état dédié** clairement signalé à l'utilisateur, qui conserve un accès partiel et lecture seule aux informations utiles (console temps réel, tableau de bord) tout en empêchant les actions susceptibles d'interférer avec le processus en cours.

---

## 2. Personas & permissions

### Personas

- **Joueur / propriétaire de serveur** — utilise l'interface web, navigue les modpacks, déclenche installation et désinstallation.
- **Co-administrateur de serveur** — invité par le propriétaire ; ses droits sur le module sont configurables via le système de permissions du panel et, si un module d'invitations / de gestion de sous-utilisateurs est présent, doivent y être correctement référencés (voir §2.2).
- **Administrateur de la plateforme** — configure le module au niveau global : fournit la clé d'API du marché qui en exige une, et choisit quels types de serveurs de jeu peuvent recevoir des modpacks.
- **Client API tiers** — script ou intégration externe qui consomme l'API publique du module pour automatiser découverte ou installation.

### 2.1 Permissions logiques (par serveur)

Trois capacités distinctes, attribuables indépendamment :

| Capacité | Permet de |
|----------|-----------|
| **Consultation** | Voir le modpack actuellement installé, parcourir, rechercher, lister les versions disponibles |
| **Installation** | Déclencher une installation ou une mise à jour de modpack |
| **Désinstallation** | Retirer le modpack et revenir à un serveur vanilla |

Ces permissions ne sont pertinentes que pour les serveurs dont le type d'environnement de jeu est compatible avec le module (voir §3) ; sur les serveurs incompatibles, l'onglet et l'API doivent être inaccessibles.

### 2.2 Intégration avec un module d'invitations / sous-utilisateurs

Si le panel hôte dispose d'un module distinct gérant les invitations de co-administrateurs ou la délégation fine de permissions à des sous-utilisateurs, le module modpack **doit détecter sa présence à l'exécution** et y enregistrer ses trois capacités de manière à ce qu'elles apparaissent automatiquement :

- dans les écrans de configuration des permissions au moment de l'invitation d'un sous-utilisateur ;
- dans les écrans de gestion des permissions des sous-utilisateurs existants ;
- dans toute représentation traduite des permissions visibles à l'utilisateur.

Cette intégration doit être **optionnelle et silencieuse** : si le module d'invitations n'est pas installé ou pas activé, les permissions du module modpack continuent de fonctionner via le mécanisme natif du panel sans erreur ni avertissement.

L'intitulé et la description de chacune des trois capacités doivent être traduits dans toutes les langues supportées par le module (voir §10) avant d'être exposés à un module d'invitations tiers.

---

## 3. Configuration administrateur

L'administrateur de la plateforme accède à un panneau de réglages dédié, comprenant :

1. **Clé d'API d'un marché tiers nominatif** — saisie sous forme de champ masqué (révélable), accompagnée d'un lien vers le portail développeur du fournisseur. Sans cette clé, ce fournisseur précis est inutilisable mais les autres fonctionnent.
2. **Liste blanche des types de serveur de jeu** — sélection multiple parmi les types de serveurs définis dans le panel hôte. Tant qu'aucun type n'est coché, l'onglet « Modpacks » n'apparaît sur aucun serveur. Il s'agit du mécanisme de gating principal : seuls les serveurs basés sur les types cochés bénéficient du module.

Les modifications sont persistées et s'appliquent immédiatement.

---

## 4. Inventaire de l'interface joueur

### 4.1 Emplacement

- Onglet supplémentaire dans la barre latérale de gestion d'un serveur, intitulé **Modpacks**, accompagné d'une icône évoquant un colis/paquet.
- Disponible uniquement si (a) le serveur appartient à un type autorisé par l'administrateur, et (b) l'utilisateur dispose au moins de la permission de consultation.

### 4.2 Vue principale

#### Section « Modpack actuellement installé » (conditionnelle)

S'affiche uniquement si un modpack est actuellement installé ou en cours d'installation :

- Vignette du modpack (placeholder générique si absente).
- Nom du modpack (titre).
- Étiquette du fournisseur d'origine.
- Badge de statut affiché si l'installation n'est pas terminée (libellé du type « Installation en cours »).
- Description courte tronquée à deux lignes.
- Action d'ouverture de la page publique du modpack chez le fournisseur (lien externe).
- Action de désinstallation (bouton destructif), gardée par une boîte de dialogue de confirmation explicite avertissant que tous les fichiers du serveur seront supprimés et le serveur réinstallé.

#### Section de filtres et de recherche

Toujours visible, regroupe :

- **Sélecteur de fournisseur** — six options (cf. §7), un par défaut. Le changement de fournisseur réinitialise la pagination et les autres filtres.
- **Sélecteur de version Minecraft** — affiché uniquement pour les fournisseurs supportant ce filtrage. Première option : « toutes les versions ». La liste des versions disponibles est récupérée du fournisseur.
- **Sélecteur de chargeur de mods** — affiché uniquement pour les fournisseurs supportant ce filtrage. Options : Forge, Fabric, Quilt, NeoForge, plus une option « tous ».
- **Champ de recherche libre** — soumission par touche Entrée ou bouton.
- **Sélecteur de taille de page** — trois choix discrets, du plus petit au plus grand, avec une valeur par défaut.

#### Encart d'erreur de configuration (conditionnel)

Si le joueur sélectionne le fournisseur qui requiert une clé d'API et que celle-ci n'est pas configurée, un encart explicatif remplace les résultats : il indique que l'administrateur doit configurer la clé.

#### Grille de résultats

Disposition responsive (1 colonne sur mobile, 2 sur tablette, 3 sur desktop). Chaque carte affiche :

- Vignette.
- Nom (tronqué si nécessaire).
- Petit badge mettant en avant les modpacks marqués comme adaptés à un déploiement serveur (uniquement pour les fournisseurs qui exposent cette donnée).
- Description tronquée à deux lignes.
- Action « voir sur le site du fournisseur » (lien externe).
- Action « installer » (bouton primaire), conditionnée à la permission d'installation.

#### Pagination

Affichée si plusieurs pages de résultats. Indication « page X sur Y », boutons précédent/suivant avec désactivation aux bornes.

#### États vides et d'erreur

- État vide : message « aucun modpack trouvé » accompagné d'une icône évocatrice.
- État de chargement pendant les requêtes au fournisseur.

### 4.3 Boîte de dialogue d'installation

Ouverte au clic sur « installer » :

- Titre clair indiquant l'action et le modpack visé.
- Bandeau d'avertissement signalant que la mise à jour d'un modpack peut corrompre le monde et recommandant une sauvegarde préalable.
- Sélecteur de version du modpack — chaque entrée affiche le nom de la version et, si disponibles, les versions Minecraft compatibles. Pré-filtré par le filtre de version Minecraft du parent.
- Case à cocher **« supprimer tous les fichiers du serveur avant l'installation »** — décochée par défaut, accompagnée d'un texte d'aide insistant sur le caractère irréversible.
- Bouton de confirmation, désactivé tant qu'aucune version n'est sélectionnée.

### 4.4 Notifications

- Toast de succès au déclenchement d'une installation (« installation lancée, le serveur va être réinstallé »).
- Toast de succès au déclenchement d'une désinstallation.
- Messages d'erreur explicites en cas d'absence de permission, de fournisseur non configuré, ou d'environnement serveur non compatible.

### 4.5 État global du serveur pendant l'installation d'un modpack

Pendant qu'un modpack est en cours d'installation sur un serveur, le serveur entre dans un **statut dédié** distinct du statut d'installation initiale du panel. Ce statut est exposé partout où le panel affiche habituellement le statut d'un serveur (en-tête, barre latérale, listes, API).

Comportements attendus :

- **Bandeau persistant** sur toutes les pages du serveur, expliquant en clair qu'un modpack est en cours d'installation, identifiant le modpack et la version visée, et indiquant que le serveur sera disponible à la fin du processus. Le bandeau est traduit (§10).
- **Accès maintenu en lecture seule** à :
  - Le **tableau de bord** du serveur (page d'accueil) — affichage de l'état général, ressources, identité du serveur ;
  - La **console temps réel** du serveur — flux de logs émis par l'agent local pendant le téléchargement et le déploiement ; aucune interaction (envoi de commande) n'est possible.
- **Onglets bloqués pendant l'installation** :
  - L'onglet Modpacks bascule sur la vue « modpack actuellement installé » avec son badge « installation en cours », et désactive les actions d'installation, de mise à jour et de désinstallation.
  - Tout autre onglet susceptible de modifier l'état du serveur (fichiers, sauvegardes, planificateurs, sous-utilisateurs, paramètres, base de données, réseau) est soit masqué, soit affiché dans un état désactivé avec un message contextualisé renvoyant au bandeau.
  - Les contrôles de cycle de vie du serveur (démarrage, arrêt, redémarrage) sont également désactivés.
- **Sortie automatique du statut** dès que :
  - Le travail de fond signale la finalisation de l'installation (succès) — le statut revient à l'état standard du panel ;
  - Ou le travail de fond signale un échec — le statut revient à l'état standard et l'enregistrement d'intention est nettoyé conformément au §5.5.
- **Cohérence multi-fenêtre** — si l'utilisateur a plusieurs onglets ouverts sur le panel, tous reflètent le passage en/sortie du statut sans rechargement manuel (canal temps réel ou rafraîchissement périodique au choix de l'implémentation).

Ce statut est purement applicatif (côté panel) ; il n'a pas vocation à modifier le statut natif du conteneur du serveur côté agent. Il s'agit d'une couche d'information et de garde-fou destinée à l'utilisateur.

---

## 5. Parcours utilisateur

### 5.1 Découverte et installation initiale

1. Le joueur ouvre l'onglet Modpacks de son serveur.
2. Le système charge la liste des fournisseurs et leurs capacités, puis affiche une première page de résultats avec un fournisseur par défaut.
3. Le joueur affine via la recherche textuelle et les filtres ; chaque modification déclenche une nouvelle requête paginée.
4. Le joueur clique sur « installer » d'une carte ; le système charge la liste des versions du modpack.
5. Dans la boîte de dialogue, le joueur choisit la version, décide de purger ou non les fichiers existants, puis confirme.
6. Le système enregistre une intention d'installation à l'état « en attente », fait basculer le serveur dans le statut **Installation de modpack** (§4.5), accuse réception immédiatement, et planifie un travail en arrière-plan.
7. Le joueur revient sur la vue principale, où la section « modpack actuellement installé » apparaît avec un badge « installation en cours ». Le bandeau global du §4.5 est visible sur toutes les pages du serveur ; la console temps réel reste consultable.
8. Une fois le travail de fond achevé, le statut **Installation de modpack** se termine automatiquement, le bandeau et les blocages d'onglets disparaissent, le badge « installation en cours » s'efface ; le serveur est prêt à être démarré.

### 5.2 Mise à jour vers une version plus récente

Identique au parcours d'installation, mais le joueur sélectionne le même modpack et une version plus récente. L'enregistrement d'installation est mis à jour, le serveur passe à nouveau dans le statut Installation de modpack, et le travail de fond relancé. Les fichiers existants sont systématiquement purgés (la mécanique d'installation effectue un remplacement complet).

### 5.3 Changement de modpack

Identique au parcours d'installation : le système traite la nouvelle installation comme un remplacement, écrase l'enregistrement précédent, et place à nouveau le serveur en statut Installation de modpack.

### 5.4 Désinstallation

1. Le joueur clique sur « désinstaller » dans la section du modpack actuel.
2. Une confirmation explicite avertit de la perte des fichiers serveur.
3. Validation : l'enregistrement d'installation est supprimé immédiatement (l'interface reflète aussitôt un serveur vanilla). La désinstallation peut, au choix de l'implémentation, déclencher également le statut Installation de modpack (utilisé alors comme statut générique « opération modpack en cours ») le temps de la purge et de la remise à zéro, ou s'appuyer sur le statut natif d'installation du panel.
4. Le serveur est ramené à son état initial avec le binaire le plus adapté à sa configuration originelle.

### 5.5 Reprise après échec

Si l'installation échoue côté agent local du serveur (téléchargement raté, script d'installation qui retourne en erreur), l'enregistrement d'intention est marqué comme abandonné ou supprimé, le serveur quitte le statut Installation de modpack, le badge « installation en cours » disparaît, et le joueur peut relancer une nouvelle tentative. Aucune ré-essayage automatique n'est imposé : la stratégie est explicitement « échec rapide, action manuelle pour reprendre ». Le motif de l'échec doit être consultable côté utilisateur (toast non bloquant ou entrée dans le flux d'activité du serveur).

### 5.6 Usage programmatique

Un client externe authentifié peut, via l'API publique du module, énumérer les fournisseurs et leurs capacités, paginer la recherche, lister les versions, déclencher une installation ou une désinstallation, et interroger l'état courant pour suivre la progression par sondage. Le statut Installation de modpack du serveur est également exposé par l'API du panel hôte si elle expose le statut applicatif du serveur. Aucune notification poussée n'est imposée par la spec ; un canal temps réel (websocket, SSE) est un raffinement souhaitable mais facultatif.

---

## 6. Surface API conceptuelle

L'API doit être **scopée par serveur**, authentifiée par jeton porteur, et limitée par un mécanisme générique anti-abus. Voici les opérations attendues — formulées par intention, sans imposer de chemin ou de méthode HTTP.

### 6.1 Découverte

- **Lister les fournisseurs disponibles** — retourne pour chaque fournisseur : un identifiant, un nom affichable, et un ensemble de drapeaux de capacités (filtrage par version Minecraft, filtrage par chargeur de mods, etc.).
- **Lister les versions Minecraft prises en charge par un fournisseur** — pour alimenter le sélecteur de filtre. Pertinent uniquement si le fournisseur expose cette information.
- **Vérifier l'éligibilité du serveur courant** — booléen indiquant si le type d'environnement du serveur est dans la liste blanche administrateur.

### 6.2 Recherche

- **Rechercher des modpacks** — entrées : fournisseur, page, taille de page, terme de recherche optionnel, filtre version Minecraft optionnel, filtre chargeur de mods optionnel. Sortie : liste paginée avec total, page courante, nombre de pages ; chaque entrée contient identifiant, nom, description, URL publique chez le fournisseur, URL d'icône, et un drapeau « adapté serveur » lorsque disponible.
- **Lister les versions d'un modpack** — entrées : fournisseur, identifiant du modpack, filtre version Minecraft optionnel. Sortie : liste de versions avec identifiant, libellé, et liste des versions Minecraft compatibles (potentiellement vide selon les capacités du fournisseur).

### 6.3 État de l'installation

- **Récupérer l'installation courante du serveur** — sortie : objet décrivant l'installation (fournisseur, identifiant du modpack, identifiant de version, nom, description, URL publique, URL d'icône, statut de finalisation, indicateur de statut Installation de modpack actif) ou une valeur indiquant l'absence d'installation.

### 6.4 Cycle de vie

- **Déclencher une installation ou une mise à jour** — entrées : fournisseur, identifiant du modpack, identifiant de version, drapeau de purge des fichiers existants. Comportement : crée ou met à jour un enregistrement « en attente », fait basculer le serveur en statut Installation de modpack, planifie un travail de fond, retourne immédiatement. L'opération est journalisée dans le flux d'activité du serveur.
- **Déclencher une désinstallation** — aucune entrée. Comportement : supprime l'enregistrement d'installation, planifie un travail de fond de remise à zéro, retourne immédiatement. Journalisée également.

### 6.5 Garanties attendues

- Toutes les listes doivent être paginées avec une borne supérieure raisonnable sur la taille de page.
- Les opérations de listage doivent être idempotentes et faiblement coûteuses (cache côté serveur recommandé).
- Les opérations de cycle de vie doivent être asynchrones et résistantes à un double-clic (déduplication par serveur).
- Les permissions doivent être vérifiées en amont de toute opération.
- Le passage en statut Installation de modpack et sa sortie doivent être garantis atomiques côté backend : il n'existe à aucun moment un état où l'enregistrement d'installation existe sans que le statut soit reflété, ni l'inverse.

---

## 7. Intégrations externes & matrice de capacités

Le module agrège six places de marché publiques de modpacks. Chaque fournisseur a un comportement et une couverture fonctionnelle hétérogènes. La spec **doit** préserver cette hétérogénéité (l'UI s'adapte aux capacités déclarées de chaque fournisseur) plutôt que de la masquer.

### 7.1 Fournisseurs supportés

| Fournisseur | Type d'API | Authentification | Recherche | Pagination | Filtre version Minecraft | Filtre chargeur | Marqueur « serveur » | Versions multiples |
|-------------|-----------|------------------|-----------|------------|--------------------------|-----------------|----------------------|--------------------|
| **Modrinth** | REST publique | Aucune | Oui (recherche à facettes) | Oui (offset/limit) | Oui | Oui | Oui (champ dédié) | Oui |
| **CurseForge** | REST publique | Clé d'API obligatoire (en-tête) | Oui (index/taille) | Oui | Oui | Oui (par identifiant numérique de chargeur) | Oui (déduit du fichier) | Oui |
| **ATLauncher** | GraphQL publique | Aucune | Oui | Non (page unique) | Non | Non | Non | Non (libellés sans métadonnées) |
| **Feed The Beast** | REST publique | Aucune | Oui (deux modes : terme libre, populaires) | Non | Non | Non | Non | Oui |
| **Technic** | REST publique | Aucune | Oui (avec paramètre de build du lanceur) | Non | Non | Non | Non | Non (« dernière » uniquement) |
| **VoidsWrath** | Catalogue rapatrié en bloc, filtrage local | Aucune | Non (filtrage côté client) | Non | Non | Non | Non | Non (« dernière » uniquement) |

### 7.2 Quirks publics à respecter

- **Modrinth** — distinguer les modpacks de tous les autres types de projets via le filtre de type. Le statut « serveur » dérive d'un champ sur le projet qui peut prendre plusieurs valeurs sémantiques (requis, optionnel, …) à interpréter.
- **CurseForge** — l'en-tête `User-Agent` doit être accepté par le service ; certains caractères y sont rejetés. La pagination est plafonnée par le service (limite explicite sur l'index maximal). Les chargeurs sont identifiés par des entiers ; un mapping interne est nécessaire entre les noms (Forge, Fabric, Quilt, NeoForge) et ces entiers. La liste des versions Minecraft retournées doit être filtrée pour exclure les snapshots et les libellés mêlant nom de chargeur.
- **ATLauncher** — toute la recherche passe par une seule requête GraphQL. Les icônes sont reconstruites par une convention d'URL CDN à partir d'un slug retourné par l'API.
- **Feed The Beast** — la recherche retourne des identifiants seulement ; un détail par modpack doit ensuite être récupéré individuellement. Une concurrence raisonnable est recommandée pour ce dépliage. Au moins un identifiant historique du catalogue est connu pour devoir être filtré (modpack interne sans intérêt utilisateur).
- **Technic** — un identifiant de build du lanceur officiel doit être passé à chaque requête de recherche ; un fallback statique est utile en cas d'indisponibilité du service qui le fournit. Une seule version par modpack est retournée (la « dernière »).
- **VoidsWrath** — le catalogue est rapatrié en bloc depuis une source externe ; toute la recherche et le filtrage s'effectuent en mémoire après rapatriement.

### 7.3 Service auxiliaire d'identification de version Java

Le module s'appuie sur **MCJars**, un service public d'identification de binaires de serveurs Minecraft, pour, à partir d'une empreinte cryptographique d'un binaire serveur, retrouver la version Minecraft, le type (vanilla, Forge, NeoForge, Fabric, Quilt, …) et la **version de Java requise**. Cette identification permet d'ajuster automatiquement l'environnement d'exécution du serveur après installation. En cas d'échec de la reconnaissance, l'implémentation peut recourir à toute heuristique de repli adaptée. Les résultats peuvent être mis en cache de manière prolongée car ils sont stables.

> Toute solution équivalente est acceptable : la spec décrit la **fonction** (associer un binaire serveur à la version de Java qu'il exige), pas le service.

### 7.4 Stockage des icônes et des miniatures

Les URLs d'icônes sont consommées telles que retournées par les fournisseurs. Aucune réécriture ni proxying n'est imposé. Une attention particulière est à porter à la disponibilité des miniatures côté CurseForge (champ optionnel à privilégier sur l'icône pleine taille).

---

## 8. Mécanique d'installation (vue logique)

L'installation est conceptualisée comme un workflow asynchrone en plusieurs étapes, observables par l'utilisateur via l'état d'un enregistrement d'installation portant un drapeau de finalisation et via le statut applicatif **Installation de modpack** du serveur (§4.5).

1. **Création d'intention** — un enregistrement décrit l'installation visée : serveur cible, fournisseur, identifiant du modpack, identifiant de version, drapeau de purge. Initialement marqué « non finalisé ».
2. **Bascule du statut serveur** — le serveur entre dans le statut applicatif Installation de modpack (§4.5). Cette bascule est atomique avec la création d'intention (§6.5).
3. **Préparation du serveur** — le serveur de jeu est arrêté de force ; ses fichiers sont supprimés si le drapeau le demande.
4. **Déploiement** — le système récupère l'archive de la version choisie auprès du fournisseur et la déploie dans l'arborescence du serveur. Le mécanisme exact (job d'arrière-plan, agent local, exécution distante, autre) est laissé au choix de l'implémentation selon les capacités du panel hôte.
5. **Suivi** — l'implémentation suit la progression du déploiement et met à jour le drapeau de finalisation à la fin. Le canal exact (événement, callback, sondage, websocket) n'est pas imposé. Pendant cette phase, le flux de logs émis doit être accessible via la console temps réel exposée par le panel hôte (§4.5).
6. **Détection de la version Java requise** — une empreinte cryptographique du binaire serveur déposé est calculée et soumise à un service d'identification (cf. §7.3). La version de Java retournée détermine quelle image d'environnement d'exécution sera associée au serveur. Une stratégie de repli est prévue en cas de non-reconnaissance.
7. **Ajustement de l'environnement d'exécution** — l'image associée au serveur est mise à jour si elle ne correspond pas à la version de Java requise. Le module choisit prioritairement parmi les images définies pour le type de serveur d'origine ; à défaut, une image publique générique pour la version de Java cible.
8. **Sortie du statut** — le serveur quitte le statut Installation de modpack, le bandeau et les blocages associés disparaissent.
9. **Disponibilité** — le serveur peut être démarré par l'utilisateur. Le module n'effectue pas de démarrage automatique.

### Désinstallation — workflow miroir

1. Suppression de l'enregistrement d'installation (effet immédiat sur l'interface).
2. Bascule optionnelle vers le statut Installation de modpack (utilisé comme statut générique « opération modpack en cours ») le temps de la remise à zéro.
3. Arrêt forcé du serveur, purge totale des fichiers.
4. Restauration de la configuration d'exécution d'origine, avec sélection de l'image la plus récente disponible pour le type de serveur d'origine.
5. Réinstallation propre via le mécanisme natif du panel hôte.
6. Sortie du statut Installation de modpack si activé.

### Garanties attendues

- **Atomicité côté UI** — l'utilisateur voit toujours un état cohérent (soit pas d'installation, soit installation en cours avec statut serveur reflétant cela, soit installation finalisée).
- **Restaurabilité** — toute manipulation temporaire de la configuration du serveur est garantie d'être annulée, y compris en cas d'échec. Le statut Installation de modpack est garanti d'être quitté en cas d'échec.
- **Traçabilité** — chaque déclenchement d'installation ou de désinstallation est journalisé dans le flux d'activité du serveur, avec les paramètres clés. L'entrée et la sortie du statut Installation de modpack sont également journalisées.
- **Aucun lock global** — deux serveurs différents peuvent installer en parallèle sans interférence.
- **Pas de blocage permanent** — un statut Installation de modpack ne doit jamais pouvoir « bloquer » un serveur indéfiniment ; un mécanisme de timeout côté backend garantit qu'au-delà d'une durée raisonnable sans nouvelles du travail de fond, l'enregistrement d'intention est marqué en échec et le statut est levé automatiquement.

---

## 9. Contraintes non fonctionnelles

- **Asynchronisme** — les opérations longues (installation, désinstallation, ajustement Java) sont impérativement traitées hors du cycle requête-réponse, via une file de travaux.
- **Cache** — les listes de modpacks, de versions, de versions Minecraft, et les résultats du service d'identification Java sont mis en cache à des durées appropriées à la volatilité de chaque source. La spec n'impose pas de durées concrètes, mais réclame une stratégie cohérente et invalidable.
- **Limitation de débit** — l'API publique du module est protégée par un mécanisme générique anti-abus (limites par utilisateur, par serveur ou par jeton).
- **Tolérance aux pannes des fournisseurs** — l'indisponibilité d'un fournisseur ne doit pas dégrader les autres ; l'erreur est remontée à l'utilisateur de manière compréhensible, sans détails techniques sensibles.
- **Sécurité** — la clé d'API du fournisseur qui en exige une est stockée côté serveur uniquement, jamais exposée au client. Toute URL externe insérée dans l'interface utilisateur est traitée comme contenu non fiable (échappement, ouverture en lien externe).
- **Observabilité** — journalisation côté serveur des appels aux fournisseurs (sans la clé d'API), des transitions d'état d'installation, des entrées/sorties du statut Installation de modpack et des erreurs d'identification de version Java.
- **Robustesse du statut applicatif** — le statut Installation de modpack ne doit jamais survivre à un redémarrage du backend ou à une perte du travail de fond : un mécanisme de réconciliation au démarrage du backend détecte les intentions orphelines et lève le statut associé.

---

## 10. Internationalisation

- Au minimum **anglais** et **français** doivent être pris en charge dès le premier livrable. L'architecture i18n doit permettre l'ajout de langues supplémentaires sans modification du code.
- Les chaînes traduites couvrent : titres et libellés de navigation, intitulés de filtres et d'options, libellés des actions (installer, désinstaller, voir), badges de statut, textes des boîtes de dialogue (titres, descriptions, avertissements, boutons), notifications de succès et d'erreur, descriptions des permissions (y compris pour leur exposition à un éventuel module d'invitations §2.2), libellés du panneau d'administration.
- Les chaînes spécifiques au statut Installation de modpack (§4.5) sont également traduites : libellé du statut tel qu'affiché dans l'en-tête et les listes de serveurs, contenu du bandeau persistant (avec interpolation du nom du modpack et de la version), messages contextualisés affichés sur les onglets désactivés, libellé du flux d'activité serveur pour l'entrée et la sortie du statut.
- Les messages d'erreur doivent rester explicites une fois traduits (pas de simple code d'erreur).

---

## 11. Notes pour l'implémentation

- **Couplage frontend/backend** — le frontend ne doit faire aucune supposition câblée sur les capacités d'un fournisseur ; l'opération de découverte (§6.1) sert d'autorité unique pour décider quels filtres afficher.
- **Stabilité des identifiants** — les identifiants de modpack et de version retournés par l'API doivent être ceux du fournisseur d'origine, transparents, pour permettre les liens externes et la persistance.
- **Compatibilité serveur** — la liste blanche de types de serveurs (§3) est gérée par identifiants stables côté backend ; le frontend reçoit uniquement un booléen d'éligibilité par serveur.
- **Sortie utilisateur des erreurs API tierces** — un contrat d'erreur uniforme côté backend épargne au frontend de connaître les particularités des fournisseurs.
- **Tests** — l'absence de pagination ou de filtre chez certains fournisseurs doit faire l'objet de tests d'interface explicites pour éviter la disparition silencieuse de fonctionnalités. L'entrée et la sortie du statut Installation de modpack doivent être couvertes par des tests d'intégration (succès, échec, timeout, redémarrage backend).
- **Évolutivité** — l'ajout d'un septième fournisseur doit être un branchement isolé (interface adaptateur côté backend) ne nécessitant aucune modification de l'interface utilisateur si ses capacités sont correctement déclarées.
- **Découverte de modules optionnels (§2.2)** — la détection du module d'invitations / sous-utilisateurs s'effectue à l'exécution, sans dépendance dure : un mécanisme d'introspection (présence d'une classe, d'un service ou d'un point d'extension) est utilisé pour décider d'enregistrer ou non les permissions auprès dudit module.

---

## 12. Hors-périmètre explicite

Pour éviter toute ambiguïté, les éléments suivants ne sont **pas** dans le périmètre du module et ne doivent pas être implémentés au titre de cette spec :

- Hébergement direct des fichiers de modpacks (le module est un orchestrateur, pas un miroir).
- Sauvegardes automatiques avant installation (l'utilisateur en est averti, mais l'action lui revient via un autre module du panel).
- Édition de mods individuels, gestion fine des fichiers internes du modpack.
- Gestion de comptes utilisateurs ou de licences au-delà des permissions du panel hôte.
- Notifications par e-mail ou push (toasts dans l'interface uniquement).
- Modification du statut natif du conteneur de jeu côté agent (Wings ou équivalent) — le statut Installation de modpack est une couche purement applicative côté panel.

---

*Fin de spécification.*