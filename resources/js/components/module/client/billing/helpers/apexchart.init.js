export const apexchartInit = (id, series, labels) => {
    var piechartColors = getChartColorsArray(`#${id}`);
    var options = {
        series: series,
        // series: [35, 70, 15],
        chart: {
            width: 227,
            height: 227,
            type: 'pie',
        },
        labels: labels,
        // labels: ['Ethereum', 'Bitcoin', 'Litecoin'],
        colors: piechartColors,
        stroke: {
            width: 0,
        },
        legend: {
            show: false
        },
        responsive: [{
            breakpoint: 480,
            options: {
                chart: {
                    width: 200
                },
            }
        }]
    };

    var chart = new ApexCharts(document.querySelector(`#${id}`), options);
    chart.render();

    return chart;
}


function getChartColorsArray(chartId) {
    var colors = $(chartId).attr('data-colors');
    colors = JSON.parse(colors);
    return colors.map(function(value){
        var newValue = value.replace(' ', '');
        if(newValue.indexOf('--') != -1) {
            var color = getComputedStyle(document.documentElement).getPropertyValue(newValue);
            if(color) return color;
        } else {
            return newValue;
        }
    })
}
