var botsectorApp = angular.module('botsectorApp', []);
botsectorApp.filter('formatMultiple', function () {
	return function (number) {
		var suffix = '';
		if (number > 1000)
		{
			number = number / 1000;
			suffix = 'k';
		}
		if (number > 1000)
		{
			number = number / 1000;
			suffix = 'M';
		}
		if (number > 1000)
		{
			number = number / 1000;
			suffix = 'G';
		}
		if (number < 10 && suffix)
		{
			return number.toFixed(2) + suffix;
		}
		return number.toFixed(0) + suffix;
	};
})
botsectorApp.filter('formatTime', function () {
	return function (seconds) {
		var result = [], days = 0, hours = 0, minutes = 0;
		days = Math.floor(seconds / 86400);
		if (days)
		{
			result.push(days + 'd');
			seconds -= days * 86400;
		}
		hours = Math.floor(seconds / 3600);
		if (hours)
		{
			result.push(hours + 'h');
			seconds -= hours * 3600;
		}
		minutes = Math.floor(seconds / 60);
		if (minutes)
		{
			result.push(minutes + 'min');
			seconds -= minutes * 60;
		}
		if (seconds || !result.length)
		{
			result.push(seconds + 's');
		}
		return result.join(' ');
	};
});

botsectorApp.controller('FilterController', ['$scope', '$http', function ($scope, $http) {

		var date = new Date();
		$scope.startYear = date.getFullYear();
		$scope.startMonth = date.getMonth() + 1;

		$scope.graphs = {topBots: true, fileTypes: true, botsVsBrowsersHits: true, botsVsBrowsersBandwidth: true};
		$scope.charts = [];
		$scope.loaders = {
			years: true,
			months: true,
			domains: false,
			directories: false,
			crawlers: false,
			types: false,
			topBots: false,
			fileTypes: false,
			botsVsBrowserHits: false,
			botsVsBrowsersBandwidth: false
		}

		$scope.negativeFilter = false;

		$scope.defaultLimit = 15;

		$scope.domains = [];
		$scope.domainsLimit = $scope.defaultLimit;
		$scope.domain = 0;
		$scope.directories = [];
		$scope.directoriesLimit = $scope.defaultLimit;
		$scope.directory = 0;
		$scope.crawlers = [];
		$scope.crawlersLimit = $scope.defaultLimit;
		$scope.crawler = 0;
		$scope.types = [];
		$scope.type = 0;

		$scope.errors = [];

		$scope.parser = {parsed: 0, left: 0, time: 0, status: 'waiting', computed: [], eta: 0};

		/* Get list of years/months */
		$http.get('./dispatcher.php?action=years').success(function (data) {
			$scope.loaders.years = false;
			if ($scope.check(data))
			{
				$scope.years = data;
				if (data.length)
				{
					var found = false;
					for (var year in data)
					{
						if (data[year] === $scope.startYear)
						{
							found = true;
							break;
						}
					}
					if (found === false && data[year])
					{
						$scope.startYear = data[year];
					}
				}
				$http.get('./dispatcher.php?action=months&year=' + $scope.startYear).success(function (data) {
					$scope.loaders.months = false;
					if ($scope.check(data))
					{
						$scope.months = data;
						if (data.length)
						{
							var found = false;
							for (var month in data)
							{
								if (data[month] === $scope.startMonth)
								{
									found = true;
									break;
								}
							}
							if (found === false && data[month])
							{
								$scope.startMonth = data[month];
							}
						}
					}
				});
			}
		});

		/* Update lists of entries */
		$scope.update = function () {
			var query = '&year=' + $scope.startYear + '&month=' + $scope.startMonth + '&domain=' + $scope.domain + '&directory=' + $scope.directory + '&crawler=' + $scope.crawler + '&type=' + $scope.type;
			$scope.loaders.domains = true;
			$http.get('./dispatcher.php?action=domains' + query).success(function (data) {
				$scope.loaders.domains = false;
				if ($scope.check(data))
				{
					$scope.domains = data;
				}
			});
			if ($scope.domain)
			{
				$scope.loaders.directories = true;
				$http.get('./dispatcher.php?action=directories' + query).success(function (data) {
					$scope.loaders.directories = false;
					if ($scope.check(data))
					{
						$scope.directories = data;
					}
				});
			}
			else
			{
				$scope.directories = [];
			}
			$scope.loaders.crawlers = true;
			$http.get('./dispatcher.php?action=crawlers' + query).success(function (data) {
				$scope.loaders.crawlers = false;
				if ($scope.check(data))
				{
					$scope.crawlers = data;
				}
			});
			$scope.loaders.types = true;
			$http.get('./dispatcher.php?action=types' + query).success(function (data) {
				$scope.loaders.types = false;
				if ($scope.check(data))
				{
					$scope.types = data;
				}
			});
			for (var graph in $scope.graphs)
			{
				$scope.loaders[graph] = true;
				$http.get('./dispatcher.php?action=graph&graph=' + graph + query).success(function (data) {
					if ($scope.check(data))
					{
						$scope.redraw(data);
						$scope.loaders[data.chart] = false;
					}
				});
			}
		};

		/* Apply filters */
		$scope.filter = function (type, value)
		{
			if ($scope.negativeFilter)
			{
				value = -1 * value;
				$scope.negativeFilter = false;
			}
			switch (type)
			{
				case 'negative':
					$scope.negativeFilter = true;
					break;

				case 'year':
					$scope.startYear = $scope.endYear = value;
					$http.get('./dispatcher.php?action=months&year=' + $scope.startYear).success(function (data) {
						if ($scope.check(data))
						{
							$scope.months = data;
							if (data.length > 0)
							{
								$scope.startMonth = data[0];
							}
							$scope.update();
						}
					});
					break;

				case 'month':
					$scope.startMonth = $scope.endMonth = value;
					$scope.update();
					break;

				case 'domain':
					$scope.domain = value;
					$scope.update();
					break;

				case 'crawler':
					$scope.crawler = value;
					$scope.update();
					break;

				case 'type':
					$scope.type = value;
					$scope.update();
					break;

				case 'directory':
					$scope.directory = value;
					$scope.update();
					break;
			}
			return false;
		};

		$scope.more = function (type)
		{
			switch (type)
			{
				case 'domain':
					$scope.domainsLimit = $scope.domainsLimit === $scope.defaultLimit ? 1000 : $scope.defaultLimit;
					break;

				case 'directory':
					$scope.directoriesLimit = $scope.directoriesLimit === $scope.defaultLimit ? 1000 : $scope.defaultLimit;
					break;

				case 'crawler':
					$scope.crawlersLimit = $scope.crawlersLimit === $scope.defaultLimit ? 1000 : $scope.defaultLimit;
					break;
			}
		};

		/* Redraw the graphs */
		$scope.redraw = function (data)
		{
			if ((typeof $scope.charts[data.chart]) === 'undefined')
			{
				$scope.charts[data.chart] = c3.generate({
					bindto: '#chart-' + data.chart,
					data: {
						x: 'x',
						columns: data.columns
					},
					color: {
						pattern: data.colors
					},
					axis: {
						x: {
							type: 'category',
							tick: {
								centered: true,
								rotate: 90,
								multiline: false,
								culling: {
									max: 50
								}
							}
						},
						y: {
							min: 0,
							padding: {
								top: 0,
								bottom: 0
							}
						}
					}
				});
			}
			else
			{
				$scope.charts[data.chart].load({
					columns: data.columns,
					unload: true
				});
			}
		};

		/* Check that the data is good, else show the error */
		$scope.check = function (data)
		{
			if ((typeof data) !== 'object' && (typeof data) !== 'array')
			{
				$scope.errors.push({error: data.replace(/<.+?>/g, ' ')});
				return false;
			}
			return true;
		};

		/* Parse logs */
		$scope.parse = function ()
		{
			$scope.parser.status = 'parsing';
			$http.get('./parser.php').success(function (data) {
				if ($scope.check(data))
				{
					if (data.status === 'reload')
					{
						$scope.parser.parsed++;
						$scope.parser.time = data.time;
						if (data.files && !$scope.parser.left)
						{
							$scope.parser.left = data.files;
						}
						/* Last computations */
						if ($scope.parser.computed.length >= 50)
						{
							$scope.parser.computed.shift();
						}
						$scope.parser.computed.push(data.time);
						var eta = 0;
						for (var i = 0; i < $scope.parser.computed.length; i++)
						{
							eta += $scope.parser.computed[i];
						}
						$scope.parser.eta = Math.ceil(eta / $scope.parser.computed.length * Math.max(1, $scope.parser.left - $scope.parser.parsed));
						$scope.parse();
					}
					else if (data.status === 'done')
					{
						$scope.parser.status = 'done';
						/* Only if we have parsed at least one file */
						if ($scope.parser.parsed)
						{
							$scope.update();
						}
					}
					else if (data.status === 'waiting')
					{
						$scope.parser.status = 'waiting';
						setTimeout(function () {
							$scope.parse();
						}, 10000);
					}
					else
					{
						$scope.parser.status = 'error';
						$scope.update();
					}
				}
				else
				{
					$scope.parser.status = 'error';
					$scope.update();
				}
			});
		};
		/* Allow the data to be shown even if there's some parsing going on */
		$scope.update();
		$scope.parse();
	}]);