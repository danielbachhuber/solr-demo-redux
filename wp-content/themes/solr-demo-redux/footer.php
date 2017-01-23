	<footer class="site-footer">


	</footer>

	<?php wp_footer(); ?>

	<script>
		(function($){
			var input = $('input[name="solr-enabled"]');
			input.on('change',function(){
				input.attr('disabled', 'disabled');
				$.post('/', {
					'solr-enabled': input.is(':checked') ? 'on' : 'off',
				}, function(){
					input.removeAttr('disabled');
				});
			})
		}(jQuery));
	</script>

</body>
</html>
