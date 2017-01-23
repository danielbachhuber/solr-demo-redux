	<footer class="site-footer">


	</footer>

	<?php wp_footer(); ?>

	<script>
		(function($){
			var input = $('input[name="solr-enabled"]');
			input.on('change',function(){
				input.closest('form').trigger('submit');
				input.attr('disabled', 'disabled');
			})
		}(jQuery));
	</script>

</body>
</html>
