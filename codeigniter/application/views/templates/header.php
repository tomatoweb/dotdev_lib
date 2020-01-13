<!DOCTYPE html>
<html>
<head>
	<title>CodeIgniter</title>
	<link rel="stylesheet" href="https://bootswatch.com/4/darkly/bootstrap.min.css" integrity="" crossorigin="anonymous">
	<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
	<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.0/umd/popper.min.js"></script>
	<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.1.0/js/bootstrap.min.js"></script>
	<link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">
	<link rel="stylesheet" href="<?php echo base_url();?>assets/css/style.css">
</head>
<body>

	<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
	  <a class="navbar-brand" href="<?php echo base_url();?>">CodeIgniter</a>
	  <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarColor01" aria-controls="navbarColor01" aria-expanded="false" aria-label="Toggle navigation">
	    <span class="navbar-toggler-icon"></span>
	  </button>

	  <div class="collapse navbar-collapse" id="navbarColor01">
	    <ul class="navbar-nav mr-auto">
	      <li class="nav-item <?php if(isset($title) and $title == 'Home') echo 'active' ?>">
	        <a class="nav-link" href="<?php echo base_url();?>">Home <span class="sr-only">(current)</span></a>
	      </li>
	      <li class="nav-item <?php if($this->uri->segment(1) == 'about') echo 'active' ?>">
	        <a class="nav-link" href="<?php echo base_url();?>about">About</a>
	      </li>
	      <li class="nav-item <?php if(isset($title) and $title == 'Latest Posts') echo 'active' ?>">
	        <a class="nav-link" href="<?php echo base_url();?>posts">Posts</a>
	      </li>
	      <li class="nav-item <?php if(isset($title) and $title == 'Contact') echo 'active' ?>">
	        <a class="nav-link" href="<?php echo base_url();?>contact">Contact</a>
	      </li>
	    </ul>
	    <form class="form-inline my-2 my-lg-0">
	      <input class="form-control mr-sm-2" type="text" placeholder="Search">
	      <button class="btn btn-secondary my-2 my-sm-0" type="submit">Search</button>
	    </form>
	  </div>
	</nav>

	<div class="container my-5">

