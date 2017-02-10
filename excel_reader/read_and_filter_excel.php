<?php
include 'excel_reader.php';     // include the class

// creates an object instance of the class, and read the excel file data

function sheetData($excel) {
  $nr_sheets = count($excel->sheets);       // gets the number of sheets
  $sheet = $excel->sheets[0];
  $url_array=[];
  $x = 1;
  while($x <= $sheet['numRows']) {


    $cell = isset($sheet['cells'][$x][5]) ? '/'.$sheet['cells'][$x][5] : '';
    if(empty($cell)){
      $cell = isset($sheet['cells'][$x][4]) ? '/'.$sheet['cells'][$x][4] : '';
    }
      array_push($url_array,$cell);

    $x++;
  }
  return $url_array;
}


//function sheetData($sheet) {
//  var_dump($sheet) ;
//  $re = '<table>';     // starts html table
//
//  $x = 1;
//  while($x <= $sheet['numRows']) {
//    $re .= "<tr>\n";
//    $y = 1;
//    while($y <= $sheet['numCols']) {
//      $cell = isset($sheet['cells'][$x][$y]) ? $sheet['cells'][$x][$y] : '';
//      $re .= " <td>$cell</td>\n";
//      $y++;
//    }
//    $re .= "</tr>\n";
//    $x++;
//  }
//
//  return $re .'</table>';     // ends and returns the html table
//}

//$nr_sheets = count($excel->sheets);       // gets the number of sheets
//$excel_data = '';              // to store the the html tables with data of each sheet

// traverses the number of sheets and sets html table with each sheet data in $excel_data
//for($i=0; $i<$nr_sheets; $i++) {
//  $excel_data .= '<h4>Sheet '. ($i + 1) .' (<em>'. $excel->boundsheets[$i]['name'] .'</em>)</h4>'. sheetData($excel->sheets[$i]) .'<br/>';
//}

//var_dump($excel_data);



// Excel file data is stored in $sheets property, an Array of worksheets
/*
The data is stored in 'cells' and the meta-data is stored in an array called 'cellsInfo'

Example (firt_sheet - index 0, second_sheet - index 1, ...):

$sheets[0]  -->  'cells'  -->  row --> column --> Interpreted value
-->  'cellsInfo' --> row --> column --> 'type' (Can be 'date', 'number', or 'unknown')
--> 'raw' (The raw data that Excel stores for that data cell)
*/

// this function creates and returns a HTML table with excel rows and columns data
// Parameter - array with excel worksheet data

?>