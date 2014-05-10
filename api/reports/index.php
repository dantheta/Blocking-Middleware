<?php

include_once __DIR__ . "/../1.2/libs/DB.php";

include "header.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$PAGESIZE = 20;

page_header();

?>
<?php
if (!@$_GET['rpt']) {
?>
<p>Select a report from the "available reports" menu to run.</p>
<?php
} else {

  //var_dump($REPORTS);

  $rpt = $REPORTS[$_GET['rpt']];
  //var_dump($rpt);

  echo "<h1>" . $rpt['title'] . "</h1>";
  echo "<p>" . $rpt['description'] . "</p>";

  $first = true;

  if (@$rpt['paged']) {
    $offset = @$_GET['page'] * $PAGESIZE;
    $result = $conn->query($rpt['sql'] . " limit $PAGESIZE offset $offset");
    pagination($_GET['page'], $_GET['rpt']);
  } else {
    $result = $conn->query($rpt['sql']);
  }

  echo "<table class=\"table\">\n";
  while ($rec = $result->fetch_assoc()) {

    if ($first) {
      echo "<tr>";    
      foreach($rec as $k => $v) {
        echo "<th>" . ucfirst(str_replace('_',' ',$k)) . "</th>";
      }
      $first = false;
      echo "</tr>\n";
    }
    echo "<tr>";    
    foreach($rec as $k => $v) {
      echo "<td>" . $v . "</td>";
    }
    echo "</tr>\n";

  }
  echo "</table>";
  if (@$rpt['paged']) {
    pagination($_GET['page'], $_GET['rpt']);
  }
}

page_footer();


