<?php

include_once __DIR__ . "/../1.2/libs/DB.php";
include "library.php";

$conn = new APIDB($dbhost, $dbuser, $dbpass, $dbname);

$PAGESIZE = 20;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<title>Blocked.org.uk Reports</title>
<link rel="stylesheet" href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" />
</head>
<body>

<div class="navbar navbar-inverse" role="navigation">
      <div class="container">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="#">Blocked.org.uk API Reports</a>
        </div>
        <div class="collapse navbar-collapse">
          <ul class="nav navbar-nav">
            <li class="dropdown">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown">Available Reports <b class="caret"></b></a>
              <ul class="dropdown-menu">
<?php 
foreach ($REPORTS as $name => $rpt) {
?>
                <li><a href="index.php?rpt=<?php echo $name?>"><?php echo $rpt['title']; ?></a></li>
<?php
}
?>
              </ul>
            </li>
          </ul>
        </div><!--/.nav-collapse -->
      </div>
    </div>

    <div class="container">
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
?>
    </div>

    <script src="//code.jquery.com/jquery-1.11.0.min.js"></script>
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
  </body>
</html>
<?php

function pagination($page, $name) {
  $prevpage = $page-1;
  $nextpage = $page+1;

  echo "<ul class=\"pagination\">";
  if ($prevpage >= 0) {
    echo "<li><a href=\"index.php?rpt=" . ent($name) . "&page=$prevpage\">&laquo;</a></li>";
  } else {
    echo "<li class=\"disabled\"><a href=\"\">&laquo;</a></li>";
  }
  echo "<li><a href=\"#\">$page</a></li>";

  echo "<li><a href=\"index.php?rpt=" . ent($name) . "&page=$nextpage\">&raquo;</a></li>";


}

function ent($s) {
  return htmlentities($s);
}
