<?php

require_once '../../../../wp-config.php';
require_once 'php-ofc-library/open-flash-chart.php';

global $wpdb;

$spam = array();
$max = 10;
$statistics = unserialize(get_option('spamtask_statistics_days'));
for($i=mktime(0, 0, 0, date('m'), date('d')-29, date('Y')); $i <= mktime(0, 0, 0, date('m'), date('d')+1, date('Y')); $i += 86000) {
	$date = date('m', $i).'-'.date('d', $i).'-'.date('Y', $i);
	if(is_array($statistics[$date]) AND $statistics[$date]['spam']) { $value = $statistics[$date]['spam']; }
	else { $value = 0; }
	$x = $i;
	$spam[] = new scatter_value($x, $value);
	if($value > $max) { $max = round($value*1.1); }
}
$relevant = array();
$labels = array();
for($i=mktime(0, 0, 0, date('m'), date('d')-29, date('Y')); $i <= mktime(0, 0, 0, date('m'), date('d')+1, date('Y')); $i += 86000) {
	$date = date('m', $i).'-'.date('d', $i).'-'.date('Y', $i);
	if(is_array($statistics[$date]) AND $statistics[$date]['relevant']) { $value = $statistics[$date]['relevant']; }
	else { $value = 0; }
	$x = $i;
	$relevant[] = new scatter_value($x, $value);
	if($value > $max) { $max = round($value*1.1); }
}
$steps = round($max/5);

$chart = new open_flash_chart();
$chart->set_title(new title('Spam statistics (30 days)'));

$spamDot = new dot();
$spamDot->size(4)->tooltip('#date:jS M, Y#<br>Spam messsages: #val#');
$relevantDot = new dot();
$relevantDot->size(4)->tooltip('#date:jS M, Y#<br>Relevant messsages: #val#');

$area = new area();
$area->set_width(1);
$area->set_default_dot_style($spamDot);
$area->set_colour('#ff0000');
$area->set_fill_colour('#ff8080');
$area->set_fill_alpha(0.25);
$area->on_show(new line_on_show('pop-up', 1, 0.5));
$area->set_values($spam);
$area->set_key("Spam", 10);

$chart->add_element($area);

$area = new area();
$area->set_width(2);
$area->set_default_dot_style($relevantDot);
$area->set_colour('#3cb300');
$area->set_fill_colour('#aaff80');
$area->set_fill_alpha(0.5);
$area->on_show(new line_on_show('pop-up', 0.5, 0.25));
$area->set_values($relevant);
$area->set_key("Relevant", 10);

$chart->add_element($area);

$y_axis = new y_axis();
$y_axis->set_range(0, $max);
$y_axis->labels = null;
$y_axis->set_offset(false);
$y_axis->set_steps($steps);

$x_axis = new x_axis();
$x_axis->labels = $labels;
$x_axis->set_steps(86400);
$x_axis->set_range(mktime(0, 0, 0, date('m'), date('d')-29, date('Y')), mktime(0, 0, 0, date('m'), date('d')+1, date('Y')));

$labels = new x_axis_labels();
$labels->text('#date:jS M#');
$labels->set_steps(86400);
$labels->visible_steps(2);
$labels->rotate(45);
$x_axis->set_labels($labels);

$chart->add_y_axis($y_axis);
$chart->x_axis = $x_axis;
$chart->set_bg_colour('#ffffff');

echo $chart->toPrettyString();

?>