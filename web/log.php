<!doctype html>
<?php 
date_default_timezone_set('PRC'); //Setting timezone to China 
error_reporting(0); // Close error reporting
?>
<html>
<head>
	<meta charset="utf-8"/>
	<title><?php echo $_SERVER['HTTP_HOST']; ?> - Proxy Access Log</title>
</head>
<body style="font-family:consolas;">
	<!-- HEADER -->
	<center>
		<h1><?php echo $_SERVER['HTTP_HOST']; ?></h1>
		<h3>RazerNiz proxy server access viewer</h3>
	</center>
	<hr/>
	<!-- DATE SELECTOR -->
	<div style="border:2px solid #000000; text-align:right; width:1000px; margin:20px auto;">
		<form method="post">
			<i>What dates do you want: </i>
			<select name="date">
				<?php
					$today = strtotime(date('Y-m-d'));
					$oneday = 86400;
					for($i=$today;$i>=$today-$oneday*7;$i-=$oneday) {
						echo "<option value='".date('Ymd',$i)."'>".date('Y-m-d',$i)."</option>";
					}
				?>
			</select>
			<input type="radio" name="type" value="datalog" checked />Logs
			<input type="radio" name="type" value="timechart"/>Chart
			<input type="submit" name="submit" value="View"/>
		</form>
	</div>
	<!-- INFORMATION OUTPUT -->
	<?php
		switch($_POST['type']) {
			case 'datalog':
				ReadLog($_POST['date']);
				break;
			case 'timechart':
				DrawTimeChart($_POST['date']);
				break;
			default:
				ReadLog(date("Ymd"));
		}
	?>
	<!-- FOOTER -->
	<hr style="margin-top:20px;"/>
	<center>
		<br/>Copyright &copy;2014-2016 <b>RazerNiz Netowrk Inc</b> - <a href="https://github.com/brcm/razerniz-proxy" target="_blank">@Github</a><br/>
	</center>
</body>
</html>

<?php
// Reading logfile function
function ReadLog($date) {
	// Print loging date
	echo '<div style="width:1000px; margin:0 auto;">';
	if(!isset($_POST['date'])) {
		echo "<b>Date: ".date('Y-m-d')."</b>";
	} else {
		echo "<b>Date: ".date('Y-m-d',strtotime($_POST['date']))."</b>";
	}
	// Print Table header
	echo '</div>
			<table width="1000px" border="0" cellspacing="0" style="border:1px solid #000000; margin:0 auto;" >
				<tr style="background:darkgray; color:white; font-weight:bold;">
					<td align="center">Time</td>
					<td align="center">Client IP</td>
					<td align="center">Method</td>
					<td align="center">Request Url</td>
				</tr>
		';
	// Loading loging file
	$logdir = dirname('__FILE__');
	$fp_accesslog = fopen($logdir."/fwprxy2_log/".$date.".log","r");
	// Print file in table
	if($fp_accesslog == NULL) {
		echo '<tr><td align="center" colspan="5"><h4>This day have no access log.</h4></td></tr>';
	} else {
		while(!feof($fp_accesslog)) {
			$logdata = explode("@spit@",fgets($fp_accesslog));
			echo "<tr>
					<td align='center' style='width: 90px; border-right: 1px dashed lightgray; border-bottom: 1px solid #000; background: none repeat scroll 0% 0% rgb(236, 236, 236);'>".$logdata[0]."</td>
					<td align='center' style='width: 145px; border-right: 1px dashed lightgray; border-bottom: 1px solid #000;'>".$logdata[1]."</td>
					<td align='center' style='width: 70px; border-right: 1px dashed lightgray; border-bottom: 1px solid #000; background: none repeat scroll 0% 0% rgb(236, 236, 236);'>".$logdata[2]."</td>
					<td style='width: 690px; word-break: break-all; border-bottom: 1px solid #000;'>".$logdata[3]."</td>
				</tr>
			";
		}
	}
	// Ending print
	echo '</table>';
	fclose($fp_accesslog);
}

// Draw time bar-chart
function DrawTimeChart($date) {
	// Hour count array and initizing
	$hourarr = array(0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0);
	// Loading loging file
	$logdir = dirname('__FILE__');
	$fp_accesslog = fopen($logdir."/fwprxy2_log/".$date.".log","r");
	// Print loging date
	echo '<div style="width:1000px; margin:0 auto;">';
	if(!isset($_POST['date'])) {
		echo "<b>Date: ".date('Y-m-d')."</b>";
	} else {
		echo "<b>Date: ".date('Y-m-d',strtotime($_POST['date']))."</b>";
	}
	echo '</div>';
	// Data proccess and Print
	if($fp_accesslog == NULL) {
		echo '<center><h4>This day have no access log.</h4></center>';
	} else {
		// Counting and save to array
		while(!feof($fp_accesslog)) {
			$logdata = explode("@spit@",fgets($fp_accesslog));
			$hour = explode(":",$logdata[0]);
			$hourarr[number_format($hour[0],0,"","")]++;
			$hourarr[24]++;
		}
		// Draw bar chart
		echo '<div style="width:860px; height:550px; margin:0 auto; border-left:2px solid #000000; border-bottom:2px solid #000000; position:relative;">';
		for($c=0,$i=0;$i<24;$i++) {
			// If precent less than 3 then not display
			if(($khr=number_format($hourarr[$i]/$hourarr[24]*(100),0,"",""))<3){
				$khr="";
			}
			// Draw bar and print precent data
			echo '<div style="height:'.$hourarr[$i]/$hourarr[24]*(100).'%; left:'.$c.'px; width:20px; background:lightgray; border:1px solid gray; border-bottom:0; margin:0 15px; bottom:0; position:absolute; text-align:center;"><div style="margin-top:-20px; text-align:center;">'.$hourarr[$i].'</div>'.$khr.'</div>';
			$c+=35;
		}
		echo '</div>';
		// Print Time Line
		echo '<div style="width:860px; margin:0 auto; padding-left:5px;">';
				for($i=0;$i<24;$i++) {
					echo '<div style="width:20px; margin-left:15px; float:left; text-align:center;">'.$i.'</div>';
				}
		echo '	<center><b>(Hour)</b></center>';
		echo '</div>';
	}
	// Ending print
	fclose($fp_accesslog);
}
?>