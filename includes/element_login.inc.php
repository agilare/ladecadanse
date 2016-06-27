<div id="connexion">
<form action="login.php" method="post">
<fieldset id="champs">
<legend>Connexion</legend>
<p>
<label for="pseudo">Pseudo :</label>
<input class="text" type="text" name="pseudo" id="pseudo" value="" size="10" title="Veuillez entrer votre pseudo" />
</p>
<div class="spacer"><!-- --></div>
<p>
<label for="motdepasse">Mot de Passe :</label>
<input class="text" type="password" name="motdepasse" id="motdepasse" value="" size="10" title="Veuillez entrer votre mot de passe" />
</p>
</fieldset>

<fieldset id="pied_form">
<input type="hidden" name="formulaire" value="ok" />
<input type="submit" name="Submit" value="Se connecter" class="submit" />
</fieldset>
</form>

<p id="inscription">
<a href="<?php $url_site ?>inscription.php" title="Formulaire d'inscription"><strong>Créez un compte</strong></a> afin de pouvoir ajouter vos commentaires.
</p>
</div>