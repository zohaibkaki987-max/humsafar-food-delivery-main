<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/session.php';
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$userId=(int)$_SESSION['user_id']; $restaurantId=(int)($_GET['restaurant_id']??0); $action=$_GET['action']??'toggle';
if($restaurantId<=0){header('Location: restaurants.php');exit;}
$check=$conn->prepare('SELECT id FROM restaurants WHERE id=? AND status=1 LIMIT 1'); $check->bind_param('i',$restaurantId); $check->execute(); $exists=$check->get_result()->num_rows>0; $check->close();
if(!$exists){header('Location: restaurants.php');exit;}
if($action==='remove'){$s=$conn->prepare('DELETE FROM restaurant_favorites WHERE user_id=? AND restaurant_id=?');$s->bind_param('ii',$userId,$restaurantId);$s->execute();$s->close();}
else{$s=$conn->prepare('SELECT id FROM restaurant_favorites WHERE user_id=? AND restaurant_id=? LIMIT 1');$s->bind_param('ii',$userId,$restaurantId);$s->execute();$has=$s->get_result()->num_rows>0;$s->close();if($has){$s=$conn->prepare('DELETE FROM restaurant_favorites WHERE user_id=? AND restaurant_id=?');}else{$s=$conn->prepare('INSERT INTO restaurant_favorites(user_id,restaurant_id) VALUES(?,?)');}$s->bind_param('ii',$userId,$restaurantId);$s->execute();$s->close();}
$back=$_SERVER['HTTP_REFERER']??'favorites.php'; header('Location: '.$back); exit;