<!DOCTYPE html>
<html>
<head><title>Internal Server Error</title></head>
<body>
<h1>500 Internal Server Error</h1>
<?php if(class_exists("Env") && !Env::isReal()){ ?>
<pre><?php ErrorStack::echoAll(); ?></pre>
<hr>
<pre><?php HistoryStack::echoAll(); ?></pre>
<?php }else{ ?>
<!--<?php ErrorStack::echoAll(); ?>-->
<?php } ?>
</body>
</html>