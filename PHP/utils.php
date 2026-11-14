<?php

function OuvrirConn()
{
	$db_username = 'VOTRE_UTILISATEUR';
	$db_password = 'VOTRE_MOT_DE_PASSE';

	$dsn = "oci:dbname=HOST:1521/SERVICE;charset=AL32UTF8";

	$options = [
		PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
		PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		PDO::ATTR_EMULATE_PREPARES => false,
	];
	try
	{
		$conn = new PDO($dsn,$db_username,$db_password, $options);
		return $conn;
	}
	catch (PDOException $erreur)
	{
		// echo $erreur->getMessage();
		return null;
	}
}

function ExecSelect($conn, $req)
{
	$query = $conn->prepare($req);
	$query->execute();
	$results = $query->fetchAll();
	return $results;
}

function ExecMAJ($conn, $req)
{
	$stmt = $conn->exec($req);
	return $stmt;
}

function afficherTab($obj)
{
	echo "<pre>";
	print_r($obj);
	echo "</pre>";
}
?>