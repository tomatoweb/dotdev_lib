
<h3><?php echo $post['title']; ?></h3>

<small class="text-muted">Posted on: <?php echo $post['created_at']; ?></small>

<p class="my-3"><?php echo $post['body']; ?></p>

<!-- back button -->
<button class="btn btn-success" onclick="history.go(-1);">
   <i class="fa fa-angle-left fa-1x"></i>
   back
</button>
 
<!-- form delete post -->
<?php echo form_open('posts/delete/'.$post['id']); ?>

	<button type="submit" value="delete" class="btn btn-danger float-right">delete</button>

</form>

