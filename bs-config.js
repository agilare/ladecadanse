module.exports = {
    proxy: "http://ladecadanse.local",
    files: [
        "**/*.php",            // Tous les fichiers PHP dans www et ses sous-répertoires
        "web/**/*.css",        // Tous les fichiers CSS dans public et sous-dossiers
        "web/**/*.js"          // Tous les fichiers JS dans public et sous-dossiers
    ]
};