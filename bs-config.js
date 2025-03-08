module.exports = {
    proxy: "ladecadanse.local",
    files: [
        "**/*.php",            // Tous les fichiers PHP dans la racine et ses sous-répertoires
        "web/**/*.css",        // Tous les fichiers CSS dans 'web' et sous-répertoires
        "web/**/*.js"          // Tous les fichiers JS dans 'web' et sous-répertoires
    ],
    index: "index.php"
};