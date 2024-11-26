<?php

$sess = [];

// Load existing session data from file
if (file_exists("sess.json")) {
    $sess = json_decode(file_get_contents("sess.json"), true);
}

// Start a session if not already started
if (!isset($_SESSION)) {
    session_start();
}

// Get the current session ID and timestamp
$currentSessionId = session_id();
$currentTimestamp = time();

// Update the session data with the current session ID and timestamp
$sess[$currentSessionId] = $currentTimestamp;

// Remove sessions that have been inactive for more than 1 minute
$oneMinuteAgo = $currentTimestamp - 60;
foreach ($sess as $sessionId => $timestamp) {
    if ($timestamp < $oneMinuteAgo) {
        unset($sess[$sessionId]);
    }
}

// Save the updated session data back to the file
file_put_contents("sess.json", json_encode($sess));

// Output the number of active sessions
echo count($sess);

?>
