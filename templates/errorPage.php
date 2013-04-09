<!DOCTYPE html>

<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="HandheldFriendly" content="true" />
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Error [<?php echo $_e($response->getStatusCode()); ?> - <?php echo $_e($response->getStatusReason()); ?>]</title>
  <link rel="stylesheet" type="text/css" href="css/default.css">
</head>

<body>
  <div id="wrapper">
      <div id="container">
        <div class="httpCode"><?php echo $_e($response->getStatusCode()); ?> - <?php echo $_e($response->getStatusReason()); ?></div>
        <h3>Error</h3>
        <p>An error occurred!</p>
        <div class="errorBox">
          <strong>ERROR</strong>: <?php echo $_e($e->getMessage()); ?>
        </div>
      </div><!-- /container -->
  </div><!-- /wrapper -->
</body>
</html>
