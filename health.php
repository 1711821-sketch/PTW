<?php
// Simple health check endpoint
http_response_code(200);
echo json_encode(['status' => 'ok', 'timestamp' => time()]);
