<?php
// Labels used for different portions of the plugin
$labels = array();
$labels['autoreply'] = 'Réponse automatique';
$labels['plugin_explanation'] = 'Ici vous pouvez activer une réponse automatique qui est envoyée à tous les messages que vous recevez.';
$labels['maindiv_title'] = 'Réponse automatique';
$labels['enabled'] = 'Activé';
$labels['from'] = 'De (eg. %s)';
$labels['to'] = 'À (eg. %s)';
$labels['subject'] = 'Sujet';
$labels['msg'] = 'Message';
$labels['user_dateformat'] = 'A-m-j';

// Messages used for the different portions of the plugin
$messages = array();
$messages['internal_error'] = 'Réponse automatique : Une erreur interne est survenue.';
$messages['update_error'] = 'Réponse automatique : L\'action de mise à jour pour cet utilisateur a échoué.';
$messages['insert_error'] = 'Réponse automatique : L\'action d\'insertion sur cet utilisateur a échoué.';
$messages['invalid_to_date'] = 'Réponse automatique : Vous avez fourni un "à" -date qui est dans le passé';
$messages['invalid_interval'] = 'Réponse automatique : Vous avez fourni un intervalle non valide. Le "à" -date doit être passé le "de" -date';
$messages['db_conn_err'] = 'Réponse automatique : La connexion à la base de données a été refusée. S\'il vous plaît vérifier votre fichier config.inc.php';
$messages['successfully_saved'] = 'Vous avez enregistré avec succès votre réponse automatique pour';
$messages['missing_subject'] = 'Vous avez besoin d\' un sujet avant de pouvoir enregistrer la réponse automatique';
$messages['missing_message'] = 'Vous avez besoin d\' un message à envoyer avec votre réponse automatique.';
?>