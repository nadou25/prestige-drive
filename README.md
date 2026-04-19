# PRESTIGE DRIVE — Guide de Déploiement Hostinger

## Arborescence du projet

```
public_html/
├── index.html                 ← Page d'accueil (point d'entrée)
├── mentions-legales.html      ← Mentions légales
├── cgv.html                   ← Conditions Générales de Vente
├── confidentialite.html       ← Politique de confidentialité
├── .htaccess                  ← Config Apache (HTTPS, cache, sécurité)
├── robots.txt                 ← Directives moteurs de recherche
├── sitemap.xml                ← Sitemap SEO
├── css/
│   └── styles.css             ← Feuille de style unique
├── js/
│   └── main.js                ← JavaScript unique (thème, menu, formulaire)
├── api/
│   └── send.php               ← Backend formulaire (envoi email)
├── assets/
│   ├── hero.jpg               ← ⚠️ À AJOUTER — Image hero (1920×1080 min)
│   ├── about.jpg              ← ⚠️ À AJOUTER — Photo section À Propos
│   ├── og-image.jpg           ← ⚠️ À AJOUTER — Image OpenGraph (1200×630)
│   ├── favicon.ico            ← ⚠️ À AJOUTER — Favicon
│   ├── favicon-32x32.png      ← ⚠️ À AJOUTER — Favicon 32px
│   ├── favicon-16x16.png      ← ⚠️ À AJOUTER — Favicon 16px
│   └── apple-touch-icon.png   ← ⚠️ À AJOUTER — Icône Apple 180px
└── tmp/                       ← Créé automatiquement (rate limiting)
    └── rate_limit.json        ← Auto-généré par send.php
```

---

## ⚠️ Images à fournir (OBLIGATOIRE)

Le projet utilise des images **réelles** (pas de base64). Vous devez ajouter :

| Fichier | Taille recommandée | Description |
|---------|-------------------|-------------|
| `assets/hero.jpg` | 1920×1080 px, < 300 Ko | Photo de votre véhicule (fond hero) |
| `assets/about.jpg` | 800×600 px, < 150 Ko | Photo section "À Propos" |
| `assets/og-image.jpg` | 1200×630 px | Image de partage réseaux sociaux |
| `assets/favicon.ico` | 32×32 px | Favicon classique |

**Astuce :** Compressez vos images avec [TinyJPG](https://tinyjpg.com/) ou [Squoosh](https://squoosh.app/) avant upload.

Pour générer facilement les favicons : [realfavicongenerator.net](https://realfavicongenerator.net/)

---

## Placeholders à remplacer

Cherchez et remplacez ces valeurs dans **tous les fichiers** :

| Placeholder | Fichiers concernés | Remplacer par |
|-------------|-------------------|---------------|
| `contact@votredomaine.com` | index, PHP, légales, cgv, confidentialité | Votre email pro |
| `www.votredomaine.com` | index, sitemap, robots, légales | Votre nom de domaine |
| `+33600000000` / `06 XX XX XX XX` | index, PHP, légales | Votre téléphone |
| `https://wa.me/33XXXXXXXXX` | index | Votre lien WhatsApp |
| `000 000 000 00000` | index, mentions légales | Votre SIRET |
| `AOM00000` | index, mentions légales | Votre carte VTC |
| `[Prénom Nom]` | mentions légales, confidentialité | Votre nom |
| `[Adresse complète]` | mentions légales, confidentialité | Votre adresse |
| `noreply@votredomaine.com` | api/send.php | Email expéditeur |
| `XXXXXXX` (Formspree) | index.html (commenté) | Votre ID Formspree |

---

## Déploiement sur Hostinger — Pas à pas

### Étape 1 : Préparer le ZIP
1. Ajoutez vos images dans `assets/`
2. Remplacez tous les placeholders (voir tableau ci-dessus)
3. Sélectionnez **tout le contenu** du dossier `public_html/` (pas le dossier lui-même)
4. Compressez en `.zip`

### Étape 2 : Upload sur Hostinger
1. Connectez-vous à **hPanel** (panel.hostinger.com)
2. Allez dans **Fichiers → Gestionnaire de fichiers**
3. Naviguez vers `/public_html/`
4. **Supprimez** le contenu existant (fichier par défaut de Hostinger)
5. Cliquez sur **Upload** → sélectionnez votre fichier `.zip`
6. Une fois uploadé, **clic droit → Extraire**
7. Vérifiez que `index.html` est directement dans `public_html/`

### Étape 3 : Vérification SSL/HTTPS
1. Dans hPanel → **Sécurité → SSL**
2. Vérifiez que le certificat SSL est actif (Hostinger l'active souvent automatiquement)
3. Activez **Forcer HTTPS** dans les paramètres du domaine
4. Le `.htaccess` inclus force aussi la redirection HTTPS

### Étape 4 : Tester
1. Ouvrez `https://www.votredomaine.com` dans votre navigateur
2. Vérifiez le cadenas 🔒 dans la barre d'adresse
3. Testez le formulaire de réservation
4. Testez sur mobile (Chrome DevTools → mode responsive)

---

## Choix du formulaire

### Solution A : PHP (incluse par défaut)
- Le formulaire envoie un `fetch POST` vers `./api/send.php`
- L'email est envoyé via `mail()` de PHP (supporté par Hostinger)
- Validation serveur + client + honeypot + rate limiting
- **Rien à configurer** sauf l'email dans `api/send.php`

### Solution B : Formspree (sans backend)
- Inscrivez-vous sur [formspree.io](https://formspree.io)
- Créez un formulaire et récupérez l'ID (ex: `f/xyzabc`)
- Dans `index.html`, remplacez le bloc `<form>` actuel par la version commentée (Solution B)
- Dans `js/main.js`, commentez le bloc `fetch('./api/send.php'...)` 
- Le formulaire se soumettra directement à Formspree (ou adaptez le JS pour Formspree AJAX)

---

## Checklist mise en production

### Email professionnel (SPF, DKIM, DMARC)

Pour que vos emails ne tombent pas en spam :

1. **SPF** — Ajoutez un enregistrement TXT DNS :
   ```
   v=spf1 include:_spf.hostinger.com ~all
   ```

2. **DKIM** — Dans hPanel → Emails → Configurez DKIM (Hostinger le fait souvent automatiquement)

3. **DMARC** — Ajoutez un enregistrement TXT DNS :
   ```
   _dmarc.votredomaine.com  TXT  "v=DMARC1; p=quarantine; rua=mailto:contact@votredomaine.com"
   ```

4. **Testez** votre configuration : [mail-tester.com](https://www.mail-tester.com/)

### Tests finaux

- [ ] Site accessible en HTTPS avec cadenas
- [ ] Page d'accueil s'affiche correctement
- [ ] Thème clair/sombre fonctionne
- [ ] Menu mobile fonctionne (hamburger)
- [ ] Liens de navigation smooth scroll OK
- [ ] Images chargées correctement (hero, about)
- [ ] Formulaire : soumettre → recevoir l'email
- [ ] Formulaire : validation client (champs vides, email invalide)
- [ ] Pages légales accessibles depuis le footer
- [ ] Test mobile : Chrome / Safari / Edge
- [ ] Google PageSpeed Insights > 90 (mobile)
- [ ] Vérifier robots.txt : `https://votredomaine.com/robots.txt`
- [ ] Soumettre sitemap dans Google Search Console
- [ ] Liens sociaux (WhatsApp, Facebook, Instagram, TikTok) à jour

### Performance

- Compresser les images (JPEG ~80%, WebP si possible)
- Le `.htaccess` active déjà : GZIP, cache navigateur, headers sécurité
- Pas de framework JS lourd → chargement ultra rapide

---

## Support

Pour toute question technique : contactez votre développeur ou le support Hostinger.
