<?php
// Labels used for different portions of the plugin
$labels = array();
$labels['autoreply'] = 'Auto Reply';
$labels['plugin_explanation'] = 'Hier können Sie eine automatische Antwort auf Emails einrichten, wenn Sie etwa im Urlaub sind.';
$labels['maindiv_title'] = 'Autoresponder';
$labels['enabled'] = 'Aktiviert';
$labels['from'] = 'Von (bspw. %s)';
$labels['to'] = 'Bis (bspw. %s)';
$labels['subject'] = 'Betreff';
$labels['msg'] = 'Nachricht';
$labels['user_dateformat'] = 'd.m.Y';

// Messages used for the different portions of the plugin
$messages = array();
$messages['internal_error'] = 'Autoresponder: Ein interner Fehler ist aufgetreten.';
$messages['update_error'] = 'Autoresponder Das Bearbeiten der Einstellungen schlug fehl.';
$messages['insert_error'] = 'Autoresponder: Das Einfügen in die Datenbank schlug fehl.';
$messages['invalid_to_date'] = 'Autoresponder: Es ist ein "bis"-Datum angegeben, welches in der Vergangenheit liegt';
$messages['invalid_interval'] = 'Autoresponder: Es ist ein ungültiges Intervall angegeben. Das "bis"-Datum muss nach dem "von"-Datum liegen';
$messages['db_conn_err'] = 'Autoresponder: Die Datenbankverbindung schlug fehl.';
$messages['successfully_saved'] = 'Sie haben den Autoresponder erfolgreich eingerichtet für';
$messages['missing_subject'] = 'Bitte geben Sie einen Betreff an.';
$messages['missing_message'] = 'Bitte geben Sie eine Nachricht an.';
?>