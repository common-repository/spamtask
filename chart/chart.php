<?php

$chart = '<script type="text/javascript" src="'.get_option('siteurl'). '/wp-content/plugins/spamtask/chart/swfobject.js"></script>
<script type="text/javascript">
swfobject.embedSWF("'.get_option('siteurl'). '/wp-content/plugins/spamtask/chart/open-flash-chart.swf", "my_chart", "500", "250", "9.0.0", "'.get_option('siteurl'). '/wp-content/plugins/spamtask/chart/expressInstall.swf", {"data-file":"'.get_option('siteurl'). '/wp-content/plugins/spamtask/chart/data.php"} );
</script>

<div id="my_chart"></div>';

?>