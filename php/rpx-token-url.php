<?php
// Ici, un script basique pour recevoir les données des fournisseurs (requiert PHP 5 ou plus)
// Vous devez avoir installé CURL HTTP fetching library

$JanRainApiKey = 'ff2b53c9d8106d972a6dfb265eb138f29adef6e7';  

if(isset($_POST['token'])) { // Si l'utilisateur tente de se connecter avec JanRain

	/* ÉTAPE 1 : récupérer le paramètre token */
	$token = $_POST['token'];

	/* ÉTAPE 2 : utiliser le token pour envoyer une requête vers le serveur de JanRain, qui interrogera à son tour le fournisseur */
	$post_data = array('token' => $_POST['token'],
		'apiKey' => $JanRainApiKey,
		'format' => 'json'); 

	$curl = curl_init(); // Initialisation
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Retourne le résultat du transfert au lieu de l'afficher
	curl_setopt($curl, CURLOPT_URL, 'https://rpxnow.com/api/v2/auth_info'); // On définit l'URL cible à récupérer
	curl_setopt($curl, CURLOPT_POST, true); // On dit à PHP de faire un HTTP POST (comme pour les formulaires)
	curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data); // Données à fournir par HTTP POST
	curl_setopt($curl, CURLOPT_HEADER, false); // On dit de ne pas renvoyer l'en-tête dans la valeur de retour
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // On ne vérifie pas le certificat SSL
	$raw_json = curl_exec($curl); // On exécute la requête
	curl_close($curl); // On ferme la connexion

	/* ÉTAPE 3 : décoder la réponse Json */
	$auth_info = json_decode($raw_json, true);

	if ($auth_info['stat'] == 'ok') { // Si tout est OK
  
		/* ÉTAPE 4 : récupérer les infos à partir de la réponse */
		$profile = $auth_info['profile']; // Les infos sur le membre
		$identifier = $profile['identifier']; // Le lien

		if (isset($profile['photo']))  { // Avatar
			$photo_url = $profile['photo'];
		}

		if (isset($profile['displayName']))  { // Nom à afficher
			$displayName = $profile['displayName'];
		}

		if (isset($profile['email']))  { // E-mail
			$email = $profile['email'];
		}

		session_start(); // On démarre les sessions
		// Connexion à la BDD
		$PARAM_hote='localhost'; // Le chemin vers le serveur
		$PARAM_port='3306';
		$PARAM_nom_bd='forum_bd'; // Le nom de votre base de données
		$PARAM_utilisateur='root'; // Nom d'utilisateur pour se connecter
		$PARAM_mot_passe=''; // Mot de passe de l'utilisateur pour se connecter
		try {
			$connexion = new PDO('mysql:host='.$PARAM_hote.';port='.$PARAM_port.';dbname='.$PARAM_nom_bd, $PARAM_utilisateur, $PARAM_mot_passe);
		} catch(Exception $e) {
			echo 'Erreur : '.$e->getMessage().'<br />';
			echo 'N° : '.$e->getCode();
		}
		$connexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING); // Lance une alerte à chaque requête échouée

		$nombreResultats = $connexion->query('SELECT COUNT(*) FROM forum_membres WHERE identifier = \''.$identifier.'\'')->fetchColumn(); // On vérifie si le visiteur n'est pas déjà inscrit
		if ($nombreResultats == 1) {
			$message =  '<p>Vous êtes déjà inscrit !</p><form action="./connexionjanrain.php" method="post"><input type="hidden" name="token" value="'.$token.'"/><input type="submit" value="Me connecter"/></form>'; // Message
		} else {
			if ((isset($displayName) OR isset($_POST['pseudo'])) AND (isset($email) OR isset($_POST['email']))) { // Si on a toutes les infos
				$nombreResultats = $connexion->query('SELECT COUNT(*) FROM forum_membres WHERE membre_email = \''.$email.'\'')->fetchColumn(); // On vérifie si un membre n'a pas cet e-mail
				if ($nombreResultats == 1) {
					$query = $connexion->query('SELECT membre_id, membre_pseudo FROM forum_membres WHERE membre_email = \''.$email.'\'');
					$donnees = $query->fetch(PDO::FETCH_ASSOC);
					$message = '<p>Vous pouvez vous connecter avec un compte externe en cliquant seulement sur un bouton ! Seulement, êtes-vous bien <strong>'.$donnees['pseudo'].'</strong> ? Cette question ne vous sera plus posée à l\'avenir.</p>
					<form action="./connexionjanrain.php?JanRain_lier">
					<input type="hidden" name="token" value="'.$token.'"/>
					<input type="submit" value="Oui"/> <a href="./connexion.php"><input type="button" value="Non"/></a>
					</form>'; // Message
				} else {
					if (isset($_POST['pseudo']))
						$displayName = $_POST['pseudo'];
					if (isset($_POST['email']))
						$email = $_POST['email'];
					$query = $connexion->prepare('INSERT INTO forum_membres (membre_id,membre_pseudo,membre_email,identifier) VALUES (\'\',?,?,?)'); // On enregistre les infos
					$query->execute(array($displayName, $email, $identifier));
					$_SESSION['pseudo'] = $donnees['membre_pseudo']; // On connecte le membre
					$_SESSION['id'] = $donnees['membre_id'];
					$message = '<p>Vous avez bien été inscrit !
					<a href="./index.php">Cliquez ici</a> 
					pour revenir à la page d\'accueil</p>'; // Message
				}
			} else { // Pas assez d'infos : on les demande
				if (!isset($displayName))
					$displayName = '';
				if (!isset($email))
					$email = '';
				$message = '<p>Veuillez renseigner les champs ci-dessous pour vous inscrire :</p>
				<form action="./inscriptionjanrain.php" method="post">
				<input type="hidden" name="token" value="'.$token.'"/>
				Pseudo : <input type="text" name="pseudo" value="'.$displayName.'"/>
				E-mail : <input type="text" name="email" value="'.$email.'"/>
				<input type="submit" value="M\'inscrire !"/>
				</form>';
			}
		}

	} else { /* Une erreur est survenue */
		// On affiche l'erreur
		echo 'Une erreur est survenue : ' . $auth_info['err']['msg'];
	}
}
echo $message;
?>
