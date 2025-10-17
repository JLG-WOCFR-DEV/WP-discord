# Tests manuels

## Vérifier l'export Prometheus

1. Installer et activer le plugin « Discord Bot JLG » dans une instance WordPress locale de développement.
2. Ouvrir un navigateur et se rendre sur `https://exemple.test/wp-json/discord-bot-jlg/v1/metrics`.
3. Vérifier que la réponse possède l'en-tête `Content-Type: text/plain; version=0.0.4`.
4. Confirmer que le corps de la réponse affiche les lignes Prometheus en texte brut, sans être encadrées de guillemets JSON.
