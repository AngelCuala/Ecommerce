<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel & MySQL DB Connection</title>
</head>
<body>
    <div>
        <?php
            try {
                if (DB::connection()->getPdo()) {
                    echo "Successfully connected to DB. Database name: " . DB::connection()->getDatabaseName();
                }
            } catch (\Exception $e) {
                echo "Connection failed: " . $e->getMessage();
            }
        ?>
    </div>
</body>
</html>
