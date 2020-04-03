function time_chart_update(chart, datasets, interval, animation = true) {
  var unit = getUnitByInterval(interval);

  var timeExclude = null;
  if (interval == 'hour')
    timeExclude = new Date() - (3600 * 1000);
  else if (interval == 'day')
    timeExclude = new Date() - (24 * 3600 * 1000);

  datasets.map(dataset => {
    for (i = 0; i < dataset.data.length; i++) {
      if (typeof timeExclude == 'number' && dataset.data[i].t > timeExclude)
        dataset.data.splice(i, 1);
    }
  });

  if (Array.isArray(datasets)) {
    datasets.map(function (dataset, i) {
      if (i == 0)
        dataset.fill = 'origin';
      else
        dataset.fill = undefined;
    });
  }

  if (!animation)
    chart.options.animation = false;
  chart.options.scales.xAxes = [{
    type: 'time',
    time: {
      unit: unit
    }
  }];
  chart.data.datasets = datasets;
  chart.update();
}

function chart_update(chart, labels, datasets, animation = true) {
  if (!animation)
    chart.options.animation = false;
  chart.data.labels = labels;
  chart.data.datasets = datasets;
  chart.update();
}

function time_chart(targetElement, datasets = null, interval = null) {
  var chart = new Chart(targetElement, {
    type: 'line',
    data: {
      datasets: []
    },
    options: {
      elements: {
        line: {
          fill: '-1'
        },
        point: {
          radius: 1.5
        }
      },
      scales: {
        yAxes: [{
          stacked: true
        }]
      },
      legend: {
        onClick: function(event, item) {
          if (Array.isArray(this.chart.data.datasets)) {
            this.chart.data.datasets.map(function (dataset, i) {
              if (i == 0)
                dataset.fill = 'origin';
              else
                dataset.fill = undefined;
            });
          }
          Chart.defaults.global.legend.onClick.call(this, event, item);
        }
      }
    }
  });
  if (chart && datasets && interval)
    time_chart_update(chart, datasets, interval);
  return chart;
}

function pie_chart(targetElement, labels = null, datasets = null) {
  var chart = new Chart(targetElement, {
    type: 'doughnut',
    data: {
      labels: [],
      datasets: []
    },
    options: {}
  });
  if (chart && labels && datasets)
    chart_update(chart, labels, datasets);
  return chart;
}

function bar_chart(targetElement, labels = null, datasets = null) {
  var chart = new Chart(targetElement, {
    type: 'bar',
    data: {
      labels: [],
      datasets: []
    },
    options: {
      scales: {
        yAxes: [{
          ticks: {
            beginAtZero: true
          }
        }]
      }
    }
  });
  if (chart && labels && datasets)
    chart_update(chart, labels, datasets);
  return chart;
}

function getUnitByInterval(interval) {
  switch (interval) {
    case 'fixed_interval':
      return 'minute';
    case 'hour':
      return 'hour';
    case 'day':
      return 'day';
    case 'month':
      return 'month';
    case 'year':
      return 'year';
    default:
      return null;
  }
}
