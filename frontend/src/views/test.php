<?php
error_reporting(0);
// include('file');
include("../connexion/connexion.php");
include("../function/functions.php");
// $myRandom = str_pad(rand(1,999999), 6, '0', STR_PAD_LEFT);
// echo $myRandom.'<br>';
	// if(isset($_GET['point_de_vente'])) {
		// $point_de_vente = $_GET['point_de_vente'];
	// } else {
		// $point_de_vente = '';
	// }

	if(isset($_GET['caisse'])) {
		$caisse = $_GET['caisse'];
	} else {
		$caisse = '';
	}
	function date_to_change_separation_fr2($date)
	{
		$date_en = explode("/" , trim($date)); 
		$result = $date_en[0]."-".$date_en[1]."-".$date_en[2];
		return $result;
	}
	
	if(isset($_GET['bipper'])) {
		$bipper = $_GET['bipper'];
	} else {
		$bipper = '';
	}
	
	if(isset($_GET['etage'])) {
		$etage = $_GET['etage'];
	} else {
		$etage = '';
	}


	
	
	
	//echo $caisse;
	if(isset($_GET['num_table'])) {
		$num_table = $_GET['num_table'];
	} else {
		$num_table = '';
	}
	if(isset($_GET['type_vente'])) {
		$type_vente = $_GET['type_vente'];
	} else {
		$type_vente = '';
	}
	
	
	if(isset($_GET['num_piece'])) {
		$num_piece = $_GET['num_piece'];
	} else {
		// $num_piece = $point_de_vente.$jours.$mois.$annee.$heure.$minute.$seconde;
		$num_piece = '';//$point_de_vente.$heure.$minute.$seconde;
	}

	
	// $etage = '1';
	
	if(isset($_GET['etage'])) {
		$etage = $_GET['etage'];
	} else {
		$etage = '0';
	}
	
	
	if(date("H")=='00' || date("H")=='01' || date("H")=='02' || date("H")=='03' || date("H")=='04' || date("H")=='05')
	{
		// $date1 = date("d-m-Y");
		$date1 =  date('d/m/Y', strtotime($date1.' - 1 DAY'));
		
		
		// $date2 = date("d-m-Y");
		$date2 =  date('d/m/Y', strtotime($date2.' - 1 DAY'));
		
		
		$getdate='GETDATE()-1';
	}
	else
	{
	
		if(isset($_GET['date1'])) {
			$date1 = $_GET['date1'];
		} else {
			$date1 =  date("d/m/Y");
		}
		
		if(isset($_GET['date2'])) {
			$date2 = $_GET['date2'];
		} else {
			$date2 = date("d/m/Y");
		}
		
		
		$getdate='GETDATE()';
	}
	
	
	
	if(isset($_GET['id_farany'])) {
		$id_farany = $_GET['id_farany'];
	} else {
		$id_farany = '';
	}
	
	if(isset($_GET['par_caisse'])) {
		$par_caisse = $_GET['par_caisse'];
	} else {
		$par_caisse = '';
	}
	
	
	if(isset($_GET['caisse'])) {
		$caisse = $_GET['caisse'];
	} else {
		$caisse = '';
	}
	
	
	if(isset($_GET['nom_caisse'])) {
		$nom_caisse = $_GET['nom_caisse'];
	} else {
		$nom_caisse = '';
	}
  // echo $par_caisse;
 
 
 // die();
// $call="EXEC gaaWeb_salarie_infos_standard '','',1,".$_GET['start'].",".($_GET['start']+21);

// $stmt = sqlsrv_query( $conn, $call);

	// if( $stmt === false )
	// {
		 // echo "Error in executing query.</br>";
		 // die( print_r( sqlsrv_errors(), true));
	// }

// $i=0;
	// while($res = sqlsrv_fetch_array($stmt) ) 
	// {
		// $i++;
	
	// }
	
	
	
?>

<!DOCTYPE html>
<html>
<head>
    <title>GASTRO  : POINTE DE VENTE</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Include Bootstrap CSS -->
    <link rel="stylesheet" href="../dist/css/boostrap.css">
    
    <!-- Include SmartCart CSS -->
    <link href="../dist/css/smart_cart_ok.css" rel="stylesheet" type="text/css" />
	<style>
.list-group-item2 {
    position: relative;
    /* display: block; */
    display: inline-table;
    padding: 5px 5px;
    margin-bottom: -1px;
    background-color: #fff;
    border: 0px solid #ddd;
    height: 35px;
    width: 100%;
}

.list-group-item3 {
    position: relative;
    /* display: block; */
    display: inline-table;
    padding: 5px 5px;
    margin-bottom: -1px;
    background-color: #fff;
    border-style:solid;
    border-color: #ddd;
	
	border-left:0px;
	border-right:0px;
    height: 35px;
    width: 100%;
}

	
	#corps::after{
	
	background-image:url('../photos/new-logo.png');background-repeat:no-repeat;
	left:0px;
	/*margin-left:210px*/
	background-position:245px 200px 200px 200px;
	opacity:0.9;
	z-index:-100000;
	height:1000px;position:relative
	
	}
	</style>
	<link type="text/css" href="../dist/css/datepicker.css" media="screen" rel="stylesheet"></link>
    
</head>
<body>

    <br />
    <br />
	<div id="corps">
	
	<!--<div style="margin-left:380px"><img  src="../photos/new-logo.png" width="100px" alt="..." id="logo"></div>-->
	<?php
	include("header.php");
	?>
	  <section class="container">
	<div id="contenu">
	<?php
	
	
	$select_ref_article_vendu="SELECT DISTINCT C.CL_Intitule as  CL_Intitule,C.CL_No
	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1   and E.point_de_vente='".$point_de_vente."'";
	

	
	$stmt_select_ref_article_vendu = sqlsrv_query( $conn, $select_ref_article_vendu);

	if( $stmt_select_ref_article_vendu === false )
	{
		 echo "Error in executing query 001.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}


	
	?>
	<div style="float:left;"  class="row">
        <div class="row">
		
            <div class="col-md-8" style="width:780px;">
                <div class="panel panel-default" style="box-shadow:3px 3px 12px #999">
                    <div class="panel-heading">
					    RECAPITULATIF DES VENTES du <?php echo $date1?> à <?php echo $date2?>
                    </div>
                    <div class="panel-body" style="background-color:#f1f1f1">
                        <div class="row" id="panel-body">
                            <!-- BEGIN PRODUCTS -->

	<div class="panel-footer sc-toolbar" style="width:700px;">
		
		
		
		
		
		
		<div class="date" style="width:550px;" id="date">
		<div class="" style="float:left;margin-left:10px">
			<div class=""><span class=";font-size:10px">Date debut : </span><input type="text" name="date1" id="date1" value="<?php echo date_to_change_separation_fr2($date1)?>" style="width:120px"></div>
		</div>
		<div class=""  style="float:left;margin-left:10px">
			<div class=""><span class=";font-size:10px">Date fin : </span><input type="text" name="date2" id="date2" value="<?php echo date_to_change_separation_fr2($date2)?>" style="width:120px"></div>
		</div>
		
		
		<div>
		<div class="sc-cart-toolbar" >
			<p class="btn btn-success sc-cart-checkout date_a_date" type="">OK</p> 
			
		</div>
		
		
<div class="sc-cart-toolbar">

<?php
$select_count="SELECT COUNT(*) AS NBR FROM GASTRO_COMMANDE_ENTETE WHERE etat=0 and  CONVERT(DATE,date_commande,103)=CONVERT(DATE,GETDATE(),103)";
//echo $select_count;
$stmt_select_count = sqlsrv_query( $conn, $select_count);

	if( $stmt_select_count === false )
	{
		 echo "Error in executing query 001.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	$res_stmt_select_count = sqlsrv_fetch_array($stmt_select_count);
	if($res_stmt_select_count['NBR']==0)
	{
?>
			<p class="btn btn-success sc-cart-checkout imprim_recap_all_caisse" type="">IMPRIMER</p> 
<?php
	}
	else
	{
?>
		<p class="btn btn-success sc-cart-checkout " type=""><?php echo $res_stmt_select_count['NBR'];?> Commande(s)  non payées</p> 
<?php
	}
?>
			

			
		</div>		
		
			
		</div>	
	</div>
		
		
		
		
	</div>							
<div id="smartcart3" style="width:357px;background-color:white" class="sc-cart sc-theme-default">
<input type="hidden" width="1900" name="cart_list" id="cart_list">
<span style="font-size:20px;font-weight: bold;margin-left: 2px;">LA GASTRONOMIE PIZZA</span>
	<div class="panel-heading sc-cart-heading"> <span class="sc-cart-count badge" style="background-color:green"></span> <span class="sc-cart-count badge" style="background-color:green"></span></div>
	
	<!--<div class="panel-heading sc-cart-heading" style="height:30px;padding:5px 5px 5px 5px;font-size:11px">-->
		<div class="panel-heading sc-cart-heading" style="width:350px;height:30px;padding:5px 5px 5px 5px;font-size:11px;margin-top:30px">
		
		<div style="float:left;width:135px;">D&eacute;signation</div>
		<div style="float:left;width:40px;text-align:center">P.U.</div>
		<div style="float:left;width:50px;">Qt&eacute;</div>
		<div style="float:left;width:70px;text-align:center">Montant</div>
	</div>
<div class="sc-cart-item list-group-item-print"  style="border-style:none;width:350px;">							
<?php
$i=0;
$total_total_famille=0;
while($res_stmt_select_ref_article_vendu = sqlsrv_fetch_array($stmt_select_ref_article_vendu) ) 
{

	$call="SELECT DISTINCT L.ref_article,L.design_article, SUM(L.Pu) as Pu,
		 SUM(L.quantite) as quantite,
		 SUM(L.Pu*L.quantite) as montant
		FROM GASTRO_COMMANDE_LIGNES L
		INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
		INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
		INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
		
		where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
		AND E.etat=1   
		AND C.CL_No=".$res_stmt_select_ref_article_vendu ['CL_No']."   and E.point_de_vente='".$point_de_vente."' GROUP BY L.ref_article,L.design_article";
	
											
		
		$stmt = sqlsrv_query( $conn, $call);

		if( $stmt === false )
		{
			 echo "Error in executing query 002 .</br>";
			 die( print_r( sqlsrv_errors(), true));
		}
								
							
		$famille=array();
		$ii=0;
		
		?>
		<div class="panel-heading sc-cart-heading" style="height:30px;padding:5px 5px 5px 5px;font-size:11px;background-color:#FFF;font-weight:bold;font-size:14px"><?php echo utf8_encode($res_stmt_select_ref_article_vendu ['CL_Intitule']).'';?></div>
		
		<?php
		$total_famille=0;
		$total_qte=0;
			while($res_famille_article = sqlsrv_fetch_array($stmt) ) 
			{
				
				$select_pu = "SELECT AR_PrixVen as Pu FROM F_ARTICLE WHERE  AR_Ref='".$res_famille_article['ref_article']."'";
				$stmtselect_pu = sqlsrv_query( $conn, $select_pu);

				if( $stmtselect_pu === false )
				{
					 echo "Error in executing query 002 .</br>";
					 die( print_r( sqlsrv_errors(), true));
				}
				$res_stmtselect_pu = sqlsrv_fetch_array($stmtselect_pu);
				
			$total_famille+=$res_famille_article['montant'];
			$total_qte+=$res_famille_article['quantite'];
				?>
				<div class="sc-cart-item list-group-item2" data-unique-key="1607590700957">
				
					<div class="list-group-item-heading" style="float:left;width: 140px;"><?php echo utf8_encode($res_famille_article['design_article'])?></div>
					<div class="sc-cart-item-summary" style="float:left;width:40px;text-align:center"><?php echo format_montant($res_stmtselect_pu['Pu'])?></div>  
					<div style="float:left;width:50px;text-align:center">
						<input type="number" min="1" max="1000" class="sc-cart-item-qty" value="<?php echo $res_famille_article['quantite']?>" style="display:none"><span  style="display:block"><?php echo $res_famille_article['quantite']?></span>
					</div> 
					<div style="float:left;width:70px;text-align:center"  class="sc-cart-item-amount">
					<?php 
					
					echo ''.format_montant($res_famille_article['montant']);
					
					
					//echo '<br>'.$res_famille_article['date_paiement'];
					?>
					</div>
					<div style="float:left;width:20px;text-align:center;font-size:9px;color:red"  class="sc-cart-item-amount">
					<?php 
					echo $res_famille_article['date_paiement'];
					?>
					</div>
				</div>
			<?php								
				$ii++;	
			}
			?>
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">SOUS TOTAL</div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:left"><?php echo $total_qte?>
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount"><?php echo format_montant($total_famille)?></div>

	</div>
	
<?php
								
								
$total_total_famille+=$total_famille;
$i++;

}

							
?>
<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
	<div class="list-group-item-heading" style="float:left;width: 150px;">TOTAL </div>
	<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
	<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
		
	</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_total_famille" data-total_famille="<?php echo $total_total_famille?>"><?php echo format_montant($total_total_famille)?></div>

</div>
	
<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
	
	<div class="list-group-item-heading" style="float:left;width: 150px;"> </div>
	<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
	<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
		
	</div>  <div style="float:left;width:50px" class="sc-cart-item-amount"></div>

</div>
	<?php
	
	
	$total_espece="SELECT SUM((L.quantite*L.Pu)) as montant_espece
	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1   and E.type_paiement='espece' AND L.offert=0  and E.point_de_vente='".$point_de_vente."'";
	
	
	
	$total_mvola="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement='Mvola' AND L.offert=0  and E.point_de_vente='".$point_de_vente."'"; 
	
	
	$total_airtel="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement LIKE '%Airtel%' AND L.offert=0  and E.point_de_vente='".$point_de_vente."'"; 
	
	
	$total_orange="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement LIKE '%Orange%'AND L.offert=0   and E.point_de_vente='".$point_de_vente."'"; 
	
	
	$total_cheque="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement LIKE '%heque%' AND L.offert<>1 AND L.offert<>3   and E.point_de_vente='".$point_de_vente."'"; 
	
	
	$total_billet="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement LIKE '%billet%' AND L.offert<>1 AND L.offert<>3   and E.point_de_vente='".$point_de_vente."'"; 
	
	
	$total_tpe="SELECT SUM(L.montant) as total_montant	FROM GASTRO_COMMANDE_LIGNES L
	INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece
	INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article
	INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	
	where CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)
	AND E.etat=1  AND L.montant<>'0' AND E.type_paiement LIKE '%TPE%'AND L.offert=0   and E.point_de_vente='".$point_de_vente."'"; 
	
	$total_offert="SELECT SUM(A.montant_offert) as montant_offert FROM (SELECT SUM((L.quantite*L.Pu)) AS montant_offert
	 FROM GASTRO_COMMANDE_LIGNES L INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece 
	 INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article 
	 INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	where  CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)	
	AND  E.etat=1 AND L.offert=1 and E.point_de_vente='".$point_de_vente."' AND L.montant<>0 GROUP BY L.montant) A";
	
	
	$total_offert_100="SELECT SUM(A.montant_offert) as montant_offert FROM (SELECT SUM((L.quantite*L.Pu)) AS montant_offert
	 FROM GASTRO_COMMANDE_LIGNES L INNER JOIN GASTRO_COMMANDE_ENTETE E ON E.num_piece=L.num_piece 
	 INNER JOIN F_ARTICLE A ON A.AR_Ref=L.ref_article 
	 INNER JOIN F_CATALOGUE C ON C.CL_No=A.CL_No1
	where  CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)	
	AND  E.etat=1 AND L.offert=1 and E.point_de_vente='".$point_de_vente."' AND L.montant=0 GROUP BY L.montant) A";
	
	
	$select_billetage = "SELECT TOP 1 num_pv,vendeur,caisse,c_20000,c_10000,c_5000,c_2000,c_1000,c_500,c_200,c_100,c_50,c_20,Total,CONVERT(DATE,date_save,103) as date_save,status,total_complet,Mvola,airtel,orange,cheque,tpe,offert,espece,ecart FROM gastro_biettage where vendeur='".$nom_caisse."' 
	and CONVERT(DATE,date_save,103)=CONVERT(DATE,".$getdate.",103) ORDER BY date_save DESC";
	
	
	
	
	$stmt_cheque = sqlsrv_query( $conn, $total_cheque);
	$stmt_espece = sqlsrv_query( $conn, $total_espece);
	$stmt_mvola = sqlsrv_query( $conn, $total_mvola);
	$stmt_airtel = sqlsrv_query( $conn, $total_airtel);
	$stmt_orange = sqlsrv_query( $conn, $total_orange);
	$stmt_total_billet = sqlsrv_query( $conn, $total_billet);
	$stmt_tpe = sqlsrv_query( $conn, $total_tpe);
	$stmt_offert = sqlsrv_query( $conn, $total_offert);
	$stmt_offert_100 = sqlsrv_query( $conn, $total_offert_100);
	$stmt_select_billetage = sqlsrv_query( $conn, $select_billetage);
	

	if( $stmt_espece === false )
	{
		 echo "Error in executing query 003 .</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	
	if( $stmt_mvola === false )
	{
		 echo "Error in executing query 004.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	if( $stmt_airtel === false )
	{
		 echo "Error in executing query 005.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	if( $stmt_orange === false )
	{
		 echo "Error in executing query 006.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	if( $stmt_cheque === false )
	{
		 echo "Error in executing query 006.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	if( $stmt_total_billet === false )
	{
		 echo "Error in executing query 006.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	
	if( $stmt_tpe === false )
	{
		 echo "Error in executing query 006.</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	
	
	if( $stmt_offert === false )
	{
		 echo "Error in executing query 0023 .</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	
	if( $stmt_offert_100 === false )
	{
		 echo "Error in executing query 0023 .</br>";
		 die( print_r( sqlsrv_errors(), true));
	}
	
	if( $stmt_select_billetage === false )
	{
		 echo "Error in executing stmt_select_billetage.</br>";
		 die( print_r( sqlsrv_errors(), true));
		 
		 
	}
	
	$res_espece = sqlsrv_fetch_array($stmt_espece);
	$res_mvola = sqlsrv_fetch_array($stmt_mvola);
	$res_airtel = sqlsrv_fetch_array($stmt_airtel);
	$res_cheque = sqlsrv_fetch_array($stmt_cheque);
	$res_orange = sqlsrv_fetch_array($stmt_orange);
	$res_billet = sqlsrv_fetch_array($stmt_total_billet);
	$res_tpe = sqlsrv_fetch_array($stmt_tpe);
	$res_offert = sqlsrv_fetch_array($stmt_offert);
	$res_offert_100 = sqlsrv_fetch_array($stmt_offert_100);
	$aff_billetage = sqlsrv_fetch_array($stmt_select_billetage);
	
	$net_billetage=$aff_billetage['Total'];
	
	
	$offert = $res_offert['montant_offert']/2;
	
	?>

	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957" style="display:none">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">TOTAL ESPECES </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount" data-total_caisse="<?php echo $res_espece['total_montant']?>"><?php echo format_montant($res_espece['montant_espece'])?></div>

	</div>
	
	

	
	
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">MVOLA </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_mvola" data-mvola="<?php echo $res_mvola['total_montant']?>"><?php echo format_montant($res_mvola['total_montant'])?></div>

	</div>

	
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">AIRTEL </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_airtel" data-airtel="<?php echo $res_airtel['total_montant']?>"><?php echo format_montant($res_airtel['total_montant'])?></div>

	</div>
	
	
	

	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">ORANGE </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_orange"  data-orange="<?php echo $res_orange['total_montant']?>"><?php echo format_montant($res_orange['total_montant'])?></div>

	</div>
	
	
	
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">CHEQUE </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_cheque"  data-cheque="<?php echo $res_cheque['total_montant']?>"><?php echo format_montant($res_cheque['total_montant'])?></div>

	</div>
	
	
	

	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">EN COMPTE </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_billet"  data-billet="<?php echo $res_billet['total_montant']?>"><?php echo format_montant($res_billet['total_montant'])?></div>

	</div>
	
	
	
	
	

	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">TPE </div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_tpe"  data-tpe="<?php echo $res_tpe['total_montant']?>"><?php echo format_montant($res_tpe['total_montant'])?></div>

	</div>
	
	
	
	

	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">OFFERT 50%</div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_offert" data-offert="<?php echo $res_offert['montant_offert']/2?>"><?php echo format_montant($res_offert['montant_offert']/2)?></div>

	</div>
	
	
	
	
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">OFFERT 100%</div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_offert" data-offert="<?php echo $res_offert_100['montant_offert']?>"><?php echo format_montant($res_offert_100['montant_offert'])?></div>

	</div>
	
	

	<?php
	
	$net_caisse = $total_total_famille-($res_tpe['total_montant']+$res_cheque['total_montant']+$res_billet['total_montant']+$offert+$res_orange['total_montant']+$res_airtel['total_montant']+$res_mvola['total_montant']);
	
	$net_ecart = $net_billetage-$net_caisse;
	?>
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957" style="" id="div_total_espece">
		
		<div class="list-group-item-heading" style="float:left;width: 150px;">TOTAL ESPECES</div>
		<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
		<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
			
		</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_espece" data-espece="<?php echo $net_caisse?>"><?php echo format_montant($net_caisse)?></div>

	</div>
	
	
	
	
	<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957" style="" id="div_billetage_espece">
			
			<div class="list-group-item-heading" style="float:left;width: 150px;">BILLETAGE ESPECES</div>
			<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
			<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
				
			</div>  <div style="float:left;width:50px" class="sc-cart-item-amount class_billetage" data-billetage="<?php echo $net_caisse?>"><?php echo format_montant($net_billetage)?></div>

		</div>
		
		
		
		
		<div class="sc-cart-item list-group-item3" data-unique-key="1607590700957" style="" id="div_total_ecart">
			
			<div class="list-group-item-heading" style="float:left;width: 150px;">ECART</div>
			<div class="sc-cart-item-summary" style="float:left;width:50px;text-align:right">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>  
			<div style="float:left;width:50px;text-align:center">&nbsp;&nbsp;&nbsp;
				
			</div>  <div style="float:left;width:50px;color:red" class="sc-cart-item-amount class_ecart" data-ecart="<?php echo $net_ecart?>"><?php echo format_montant($net_ecart)?></div>

		</div>
	
                      
                            <!-- END PRODUCTS -->
                        </div>
                    </div>
                </div>
                
            </div>
            
            
        </div>
		
		
		
		
		
		<?php
		
		$date1 = date("d/m/Y");
		$date2 = date("d/m/Y");
$select_="SELECT 0 as DO_Domaine,6 as DO_Type,
 E.num_piece as DO_Piece,CONVERT(VARCHAR,E.date_paiement,103)+' '+CONVERT(VARCHAR,E.date_paiement,14) as DO_Date,
  E.point_de_vente as DO_Tiers,E.point_de_vente as CA_Num,L.ref_article as AR_Ref, L.design_article as DL_design,
  L.quantite as DL_Qte, L.montant as DL_MontantHT, L.Pu as DL_PrixUnitaire,CASE WHEN (L.offert)=1 THEN 'Offert' ELSE E.type_paiement END as type


FROM GASTRO_COMMANDE_LIGNES L
INNER JOIN GASTRO_COMMANDE_ENTETE E  ON E.num_piece=L.num_piece
WHERE  CONVERT(VARCHAR,E.date_paiement,103)=CONVERT(VARCHAR,".$getdate.",103)  and E.etat=1 and L.offert<>3 ORDER BY L.num_piece ASC";
  // echo $select_;

$stmt_select_ = sqlsrv_query( $conn, $select_);

	if( $stmt_select_ === false )
	{
		 echo "Error in executing query.</br>";
		 die( print_r( sqlsrv_errors(), true));
		 
		 
	}
	
	
	
$i=0;
$array = array();
$tab="";
while($res = sqlsrv_fetch_array($stmt_select_)) 
{
	

	
	$tab.= $res['DO_Domaine'].','.$res['DO_Type'].','.$res['DO_Piece'].','.$res['AR_Ref'].','.$res['DL_design'].','.$res['DO_Date'].','.$res['DO_Tiers'].','.$res['CA_Num'].','.$res['DL_Qte'].','.$res['DL_PrixUnitaire'].','.$res['type'].'|';
	
	
	

	

	
	$i++;
}






		
		?>
		
		
		
		
		
		
		<div class="row">
		
            <div class="col-md-8" style="width:580px">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        ...
                    </div>
                    <div class="panel-body" id="ajax_produit">
                        
                    </div>
                </div>
                
            </div>
            
           
        </div>
        </div>
		
		<div style="float:left;width:250px;margin-left:5px">
		
		<aside class="col-mdd-3">
                
                <!-- Cart submit form -->
                <form action="resultats.php" method="POST" id="form_panier"> 
                    <!-- SmartCart element -->
                   <?php 
			  if($type_vente=='table')
			  {
					if($num_table=="")
				    {
						include("panier_default.php");
				    }
				    else
				    {
						include("panier_encours.php");
				    }
			  }
			  else
			  {
				//include("panier_recapitulatif.php");
			  }
			  
				  
				   
				   
				   
				   
				   ?>
                </form>
                
            </aside>
		
		</div>
		
     </div>
    </section>
    </div>
    <!-- Include jQuery -->
    <!--<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js" type="text/javascript" ></script>-->
    <!-- Include SmartCart -->

    <!-- Initialize -->
	
	
	    <script src="../dist/js/jquery.js" type="text/javascript"></script>
    <script src="../dist/js/jquery.smartCart.js" type="text/javascript"></script>
   <script src="../dist/js/jquery_date.js" type="text/javascript"></script>
   <script type="text/javascript" src="../dist/js/drag.js"></script>

<script type="text/javascript" src="../dist/js/datepicker_fr.js"></script>
<script></script>



<script>

	var num_piece = '<?php echo $num_piece;?>';
	var text = '<?php echo $tab;?>';
	var pv = '<?php echo $point_de_vente;?>';
	 var caisse = '';
	 var type_paiement = '';


	$.ajax
	({
	   type: "GET",
	   url: "http://41.188.38.173:9999/_template/getCaisse_export_sage.php",
	   // url: "http://192.168.88.105:7777/_template/getCaisse_export_sage.php",
	   async: true,
	   data: "test="+text+"&PV="+pv+"&caisse="+caisse+"&type_paiement="+type_paiement+"&num_piece="+num_piece,
	   success: function(msg)
	   {
			// alert(msg)
			
				
									
									
	   }
	   ,
		error: function(msg)
		{
			
		}
	});
				
				


</script>


<script>



$(document).ready(function(){

//alert('query')
		$('input#date1').datepicker();
		$('input#date2').datepicker();
		// test date_fin >= date_debut
		$('input#date1').change(function(){
			var date_deb = $('input#date1').val().replace(/^\s+/g,'').replace(/\s+$/g,'');
			//alert (date_deb);
			date_deb = date_deb.split("-");
			var date_deb_en = date_deb[2] + "-" + date_deb[1] + "-" + date_deb[0];
			
			var date_fin = $('input#date2').val().replace(/^\s+/g,'').replace(/\s+$/g,'');
			date_fin = date_fin.split("-");
			var date_fin_en = date_fin[2] + "-" + date_fin[1] + "-" + date_fin[0];
			
			if (date_fin_en < date_deb_en)
			{
				alert('La date fin doit etre sup\351rieure ou \351gale \340 la date d\351but.');
				$(this).val() = '';
			}
		});
		
		$('input#date2').change(function(){
			var date_deb = $('input#date1').val().replace(/^\s+/g,'').replace(/\s+$/g,'');
			//alert (date_deb);
			date_deb = date_deb.split("-");
			var date_deb_en = date_deb[2] + "-" + date_deb[1] + "-" + date_deb[0];
			
			var date_fin = $('input#date2').val().replace(/^\s+/g,'').replace(/\s+$/g,'');
			date_fin = date_fin.split("-");
			var date_fin_en = date_fin[2] + "-" + date_fin[1] + "-" + date_fin[0];
			
			if (date_fin_en < date_deb_en)
			{
				alert('La date fin doit etre sup\351rieure ou \351gale \340 la date d\351but.');
				$(this).val() = '';
			}
		});
	});
	
	
</script> 

<?php

if(!isset($_GET['terminer']))
{
?>

	<script>
	// alert('testDfffffddddddddddddddseee')

$(document).ready(function(){
	
	  // alert('JJJJJJJiquer')
	  var espece = $('div.class_espece').attr('data-espece');
	  var mvola = $('div.class_mvola').attr('data-mvola');
	  var airtel = $('div.class_airtel').attr('data-airtel');
	  var orange = $('div.class_orange').attr('data-orange');
	  var cheque = $('div.class_cheque').attr('data-cheque');
	  var tpe = $('div.class_tpe').attr('data-tpe');
	  var offert = $('div.class_offert').attr('data-offert');
	  var total_famille = $('div.class_total_famille').attr('data-total_famille');
	 
	 
	  var id_farany="<?php echo $id_farany ?>";
	  var point_de_vente="<?php echo $point_de_vente ?>";
	  var par_caisse="<?php echo $par_caisse ?>";
	  var caisse="<?php echo $caisse ?>";
	  var nom_caisse="<?php echo $nom_caisse ?>";
	 
	 // alert(total_famille)
	 
	 // alert(mvola)
	 // alert(airtel)
	 // alert(orange)
	 // alert(cheque)
	 // alert(tpe)
	
	 // alert(par_caisse)
	 // alert(nom_caisse)
	 
	// alert(espece) 
	
			$.ajax
				({
				   type: "POST",
				   url: "save_billetage.php?raz=1&caisse="+caisse,
				   data: "total_famille="+total_famille+"&mvola="+mvola+"&airtel="+airtel+"&orange="+orange+"&cheque="+cheque+"&tpe="+tpe+"&offert="+offert+"&espece="+espece+"&id_farany="+id_farany+"&point_de_vente="+point_de_vente,
				   success: function(msg)
				   {
							
							
							// alert(msg)
// $("#smartcart3").html(msg);
  // location.reload(true);
  // window.setTimeout('location.reload()', 1000);
						// if(par_caisse=='0')
						// {
							  window.location.href = "recap_des_ventes_tous_les_caisses.php?type_vente="+type_vente+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&nom_caisse="+nom_caisse+"&par_caisse="+par_caisse+"&terminer=1";
						// }
						// else
						// {
							 // window.location.href = "recap_des_ventes_caissier.php?type_vente="+type_vente+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&nom_caisse="+nom_caisse+"&par_caisse="+par_caisse;
							
						// }
						 
												
				   }
				   ,
					error: function()
					{
						alert('erreur');
					}
				});
	
	
})


</script>

<?php

}
?>


<script>
	
	$(".sc-product-item_table").click(function(){
	
		//alert('test')
		var num_table  =  $(this).find('p.numero_table').text();
		var caisse = '<?php echo $caisse?>';
		var etage = '<?php echo $etage?>';
		var bipper  = '';
		//alert(num_table)
		$(this).addClass("table_occupe");
		var table='table';
		window.location.href = "famille_article.php?type_vente="+table+"&num_table="+num_table+"&caisse="+caisse+"&etage="+etage+"&bipper="+bipper;
	
   
	});
	
	$(".sc-product-item_table_rajouter").click(function(){
	
		//alert('test')
		var bipper  = '<?php echo $bipper?>';
		 // alert(bipper)
		
		// var bipper  = $(this).attr('id');
		// alert(bipper)
		
		var num_table  =  '<?php echo $num_table?>';
		var caisse = '<?php echo $caisse?>';
		var table = '<?php echo $table?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var type_vente = '<?php echo $type_vente?>';
		var num_piece = '<?php echo $num_piece?>';
		var etage = '<?php echo $etage?>';
		//var num_piece = $(this).attr('id');
		//alert(num_piece)
		//alert(num_table)
		//$(this).addClass("table_occupe");
		//var table='table';
		window.location.href = "famille_article.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&num_piece="+num_piece+"&bipper="+bipper+"&etage="+etage;
	
   
	});
	
	
	$(".sc-product-item_table_occupe_event").click(function(){
	
		//alert('test')
		var num_table  =  $(this).find('p.numero_table').text();
		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		// var num_piece = '<?php echo $num_piece?>';
		var etage = '<?php echo $etage?>';
		var bipper  = $(this).attr('id');
		var num_piece  = $(this).attr('data-numpiece');
		  // alert(num_piece)
		$(this).addClass("table_occupe");
		//var table='table';
		window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&etage="+etage+"&bipper="+bipper+"&num_piece="+num_piece;
	
   
	});
	
	$("#change_table").click(function(){
	
		//alert('test')
		// var num_table  =  $(this).find('p.numero_table').text();
		
		// var bipper  = $('#affiche_bipper').text();
		var bipper  = '<?php echo $bipper?>';
		// alert(bipper)
		var num_table  =  '<?php echo $num_table?>';
		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var num_piece = '<?php echo $num_piece?>';
		var etage = '<?php echo $etage?>';
		//alert(num_table)
		$(this).addClass("table_occupe");
		//var table='table';
		window.location.href = "change_table.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&bipper="+bipper+"&etage="+etage+"&num_piece="+num_piece;
		
	
	});
	
	
	$(".sc-etage").click(function(){
	
		//alert('test')
		// var num_table  =  $(this).find('p.numero_table').text();
		var etage = $(this).attr('id');
		//alert(etage)
		
		var num_table  =  '<?php echo $num_table?>';
		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var num_piece = '<?php echo $num_piece?>';
		var bipper  = '<?php echo $bipper?>';
		// alert(num_table)
		$(this).addClass("table_occupe");
		//var table='table';
		// window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente;
		window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&etage="+etage+"&bipper="+bipper+"&num_piece="+num_piece;
   
	});
	
	$("#logo").click(function(){
	
		//alert('test')
		// var num_table  =  $(this).find('p.numero_table').text();
		
		// window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente;
		window.location.href = "accueil.php";
   
	});
	$(".sc-product-item_table .table_occupe").click(function(){
	
		alert('test')
		$(this).addClass("table_occupe2");
	
   
	});

	
	
	
	
	
	$("p#imprim_recapp").click(function(){

		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		/*$("#smartcart3").css('bordertyle','none');	
						var restore = $('body').html();
						var print = $('#smartcart3').clone();
						$("BODY").css('width','300');
						$('body').empty().html(print);
						window.print();
						$("BODY").css('width','');
						
						$('body').html(restore);*/
						
						
						
						
						
						$("#smartcart3").css('border-style','none');	
						$("#smartcart3").css('width','');	
						var restore = $('body').html();
						var print = $('#smartcart3').clone();
						$("BODY").css('width','300pt');
						$('body').empty().html(print);
						window.print();
						$("BODY").css('width','');
						
						
						$('body').html(restore);
						$("#smartcart3").css('width','');
		
   
	});
	
	
	
	
	
	
	
	
	
	
	
	
	$("p.imprim_recap").click(function(){
	
		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var date1='<?php echo $date1?>';
		var date2='<?php echo $date2?>';
		// alert(date1)
		$.ajax
				({
				   type: "POST",
				   url: "ajax_recap_des_ventes.php?caisse="+caisse+"&point_de_vente="+point_de_vente+"&date1="+date1+"&date2="+date2,
					/*data: "num_piece="+num_piece+"&num_table="+num_table+"&type_vente="+type_vente+"&type_paiement="+type_paiement,*/
				   success: function(msg)
				   {
						
							
							$("#smartcart3").html(msg);
						

						// $("#smartcart3").css('border-style','none');	
						// $("#smartcart3").css('width','300pt');	
						// var restore = $('body').html();
						// var print = $('#smartcart3').clone();
						// $("BODY").css('width','300pt');
						// $('body').empty().html(print);
						// window.print();
						// $("BODY").css('width','');
						
						
						
						$("#smartcart3").css('border-style','none');	
						$("#smartcart3").css('width','');	
						var restore = $('body').html();
						var print = $('#smartcart3').clone();
						$("BODY").css('width','200pt');
						$('body').empty().html(print);
						window.print();
						
						
						
						// $('body').html(restore);
						// $("#smartcart3").css('width','357px');
							
							
							
						$("BODY").css('width','');
						
						
						
						
						$('body').html(restore);
						//window.print();
						$("#smartcart3").css('width','');
						// $("#facture_biper").css('display','none');	


							
												
												
				   }
				   ,
					error: function()
					{
						alert('erreur');
					}
				});
   
	});
	
	$("p.imprim_recap_all_caisse").click(function(){
	
		var caisse = '<?php echo $caisse?>';
		var nom = '<?php echo $nom?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var date1='<?php echo $date1?>';
		var date2='<?php echo $date2?>';
		var id_farany="<?php echo $id_farany ?>";
		 // alert(date1)
		$.ajax
				({
				   type: "POST",
				   url: "ajax_recap_des_ventes_all_caisse.php?caisse="+caisse+"&point_de_vente="+point_de_vente+"&date1="+date1+"&date2="+date2+"&id_farany="+id_farany+"&nom="+nom,
					/*data: "num_piece="+num_piece+"&num_table="+num_table+"&type_vente="+type_vente+"&type_paiement="+type_paiement,*/
				   success: function(msg)
				   {
						
							
							 // $("#smartcart3").html(msg);
						

						// $("#smartcart3").css('border-style','none');	
						// $("#smartcart3").css('width','300pt');	
						// var restore = $('body').html();
						// var print = $('#smartcart3').clone();
						// $("BODY").css('width','300pt');
						// $('body').empty().html(print);
						// window.print();
						// $("BODY").css('width','');
						
						
						
						// $("#smartcart3").css('border-style','none');	
						// $("#smartcart3").css('width','');	
						// var restore = $('body').html();
						// var print = $('#smartcart3').clone();
						// $("BODY").css('width','200pt');
						// $('body').empty().html(print);
						// window.print();
						
						
						
						// $('body').html(restore);
						// $("#smartcart3").css('width','357px');
							
							
							
						// $("BODY").css('width','');
						
						
						window.location.href = "accueil.php?type_vente="+type_vente+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&data_envoie=1";
						
						  $('body').html("Cette machine va s'etteindre automatiquement");
						
						  
						//window.print();
						// $("#smartcart3").css('width','');
						// $("#facture_biper").css('display','none');	


							
												
												
				   }
				   ,
					error: function()
					{
						alert('erreur');
					}
				});
				
				
				
				
				var pv = '<?php echo $point_de_vente;?>';
				var caisse = '<?php echo $caisse;?>';
				// alert(pv)
			$.ajax
				({
				   type: "GET",
				   url: "http://41.188.38.173:9999/_template/getCaisse2_remove.php",
					async: true,
					data: "PV="+pv+"&caisse="+caisse,
				   success: function(msg)
				   {
						// alert(msg)
								
												
												
				   }
				   ,
					error: function(msg)
					{
						// alert(msg)
					}
				});
   
	});
	
	
	
	
	
	
	
	
	$("p.date_a_date").click(function(){
	
		var caisse = '<?php echo $caisse?>';
		var type_vente = '<?php echo $type_vente?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		
		
		
		var date1=$('input#date1').val();
		var date2=$('input#date2').val();
		// alert(date1);
		// alert(date2);
		// alert(caisse);
		// alert(point_de_vente);
		// alert(type_vente);
		
		
		
		
		window.location.href = "ajax_redirection_date_recap_all_caisse.php?caisse="+caisse+"&point_de_vente="+point_de_vente+"&date1="+date1+"&date2="+date2+"&terminer=1";					
		
		
		
   
	});
	
	
	
	
	
	
	
	
	
	
	
	
	
	$(".choisir_type").click(function(){
		
		$("#type_paiement").css('display','block');
		
	});
	
	$(".sc-cart-remove").click(function(){
	
		var num_table  =  '<?php echo $num_table?>';
		var caisse = '<?php echo $caisse?>';
		var table = '<?php echo $table?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var type_vente = '<?php echo $type_vente?>';
		var num_piece = '<?php echo $num_piece?>';
		var ref_article = $(this).attr('id');
		var bipper  = '<?php echo $bipper?>';
		var etage  = '<?php echo $etage?>';
		
		// alert(ref_article)
		if ( confirm( "Voulez-vous vraiement supprimer cette article ? " ) ) {
		
			$.ajax
				({
				   type: "POST",
				   url: "ref_article_traitement.php?remove=1",
					data: "ref_article="+ref_article+"&num_piece="+num_piece,
				   success: function(msg)
				   {
						// alert(msg)
							// $("#contenu").load();
							// $("#smartcart2").html(msg);
							// $(".panel-body").html('PAIEMENT EFFECTUE POUR LA PIECE <B>'+num_piece+'</B> DE LA TABLE <b>'+num_table+'</b>');
							
						// window.location.href = "change_table.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente;	
						// window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente;		
							window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&num_piece="+num_piece+"&bipper="+bipper+"&etage="+etage;							
												
				   }
				   ,
					error: function()
					{
						alert('erreur');
					}
				});
    // Code à éxécuter si le l'utilisateur clique sur "OK"
		} else {
			// Code à éxécuter si l'utilisateur clique sur "Annuler" 
		}
		
		
   
	});
	
	
	
	
	$(".offert").click(function(){
	
		//alert('TEST AJAX')
		var num_table  =  '<?php echo $num_table?>';
		var caisse = '<?php echo $caisse?>';
		var table = '<?php echo $table?>';
		var point_de_vente = '<?php echo $point_de_vente?>';
		var type_vente = '<?php echo $type_vente?>';
		var num_piece = '<?php echo $num_piece?>';
		var bipper = '<?php echo $bipper?>';
		var etage = '<?php echo $etage?>';
		var ref_article = $(this).attr('id');
		// alert(num_piece)
		
		// alert(type_vente)
		// alert(num_piece)
		// alert(ref_article)
		if ( confirm( "Voulez-vous vraiement offert cette article ? " ) ) {
		
		
		$.ajax
				({
				   type: "POST",
				   url: "ref_article_traitement.php?offert=1",
					data: "ref_article="+ref_article+"&num_piece="+num_piece+"&point_de_vente="+point_de_vente+"&type_vente="+type_vente+"&caisse="+caisse+"&etage="+etage+"&bipper="+bipper,
				   success: function(msg)
				   {
						
							
						window.location.href = "vente_encours.php?type_vente="+type_vente+"&num_table="+num_table+"&caisse="+caisse+"&point_de_vente="+point_de_vente+"&num_piece="+num_piece+"&bipper="+bipper+"&etage="+etage;	
							
												
												
				   }
				   ,
					error: function()
					{
						alert('erreur');
					}
				});
		
		}
		
		
   
	});
















	
	</script>
</body>
</html>