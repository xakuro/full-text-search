var fullTextSearchSettings;

(function($) {
	fullTextSearchProgression = function() {
		var r, c, pct, val;
		var $progress = $('.full-text-search-settings-progress');
		var $wrapper = $progress.closest('.full-text-search-settings-progress-wrapper');
		var $progressLabel = $('.full-text-search-settings-progress-label', $wrapper);
		var $circle = $('.full-text-search-settings-progress svg #bar');

		var totalPosts = parseInt(fullTextSearchSettingsOptions.totalPosts, 0);
		var indexedPosts = parseInt(fullTextSearchSettingsOptions.indexedPosts, 0);

		if (0 === totalPosts) {
			return;
		}

		if (totalPosts === indexedPosts) {
			$wrapper.addClass('green').removeClass('orange').removeClass('loading');
			$progressLabel.text(fullTextSearchSettingsOptions.goodText);
		} else {
			if (fullTextSearchSettingsOptions.indexing) {
				$wrapper.addClass('orange').removeClass('loading');
				$progressLabel.text(fullTextSearchSettingsOptions.indexingText);
			} else {
				$progressLabel.text(fullTextSearchSettingsOptions.incompleteText);
			}
		}

		val = 100 - Math.ceil((indexedPosts / totalPosts) * 100);
		if (0 > val) {
			val = 0;
		}
		if (100 < val) {
			val = 100;
		}

		r = $circle.attr('r');
		c = Math.PI * (r * 2);
		pct = (val / 100) * c + 'px';

		$circle.css({ strokeDashoffset: pct });
	}

	fullTextSearchMaintenance = function() {
		var self = this;

		this.start = function() {
			$.ajax({
				type: 'POST',
				url: ajaxurl,
				data: { action: 'full_text_search_settings', nonce: fullTextSearchSettingsOptions.nonce },
				success: function (res) {
					$('#full-text-search-settings-maint .posts-count').text(res.posts_count);
					$('#full-text-search-settings-maint .index-count').text(res.index_count);

					if ('PROCESSING' === res.status) {
						setTimeout(self.start, 3000);
					} else if ('DONE' === res.status) {
						if (res.posts_count === res.index_count) {
							$('#full-text-search-settings-maint .message').html(fullTextSearchSettingsOptions.completeMessageText);
						} else {
							$('#full-text-search-settings-maint .message').html(fullTextSearchSettingsOptions.incompleteMessageText);
						}
						$('#full-text-search-index-sync').prop('disabled', false);
					}

					fullTextSearchSettingsOptions.totalPosts = res.posts_count;
					fullTextSearchSettingsOptions.indexedPosts = res.index_count;
					fullTextSearchSettingsOptions.indexing = ('PROCESSING' === res.status);
					fullTextSearchProgression();

					return false;
				},
			});
		};

		this.start();
	};

	fullTextSearchSettings = function() {
		var section = $('#enable-attachment'),
			filter = section.find('input:radio[value="filter"]'),
			filterCheckboxs = section.find('input:checkbox'),
			check_disabled = function() { 
				filterCheckboxs.prop('disabled', ! filter.prop('checked'));
			};
		check_disabled();
		section.find('input:radio').on('change', check_disabled);
	};

	$(document).ready(function() {
		//fullTextSearchProgression();
		fullTextSearchMaintenance();
		fullTextSearchSettings();
	});
}(jQuery));
