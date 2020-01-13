<!-- back button -->
<button class="btn btn-success" onclick="history.go(-1);">
   <i class="fa fa-angle-left fa-1x"></i>
   back
</button>
	
<div class="mx-auto w-50">
	<h3 class="my-5"><?php echo $title; ?></h3>

	<span class="text-danger"><?php echo validation_errors(); ?></span>

	<?php echo form_open('posts/create') ?>
	  <div class="form-group">
	    <label for="title">Title</label>
	    <input type="text" name="title" class="form-control" id="title" aria-describedby="title" placeholder="Enter title">    
	  </div>
	  <div class="form-group">
	    <label for="body">Body</label>
	    <textarea name="body" class="form-control" id="body" rows="3"></textarea>
	  </div>
	  <div class="form-group form-check">
	    <input type="checkbox" class="form-check-input" id="exampleCheck1">
	    <label class="form-check-label" for="exampleCheck1">Check me out</label>
	  </div>
	  <button type="submit" class="btn btn-primary">Submit</button>
	</form>
</div>