<?php
session_start();
session_unset();      // remove all session variables
session_destroy();    // destroy the session on the server
header("Location: login.html");  // or index.html
exit;