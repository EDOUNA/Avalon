@extends('mainLayout')
@section('content')
    <script type="text/javascript">
        const chartInterval = {{ $chartInterval }};
        const internalStaticInterval = "{{ url('meter/api/renderDefaultMeasurements') }}";

        $(document).ready(function () {
            let energyChart = new Chart($("#energyChart"), {
                type: 'bar',
                responsive: true,
                maintainAspectRatio: false,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'kWh',
                        data: [],
                        backgroundColor: 'rgba(255, 99, 132, 0.2)',
                        borderColor: 'rgba(255,99,132,1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                //beginAtZero: true
                            }
                        }]
                    }
                }
            });

            let gasChart = new Chart($("#gasChart"), {
                type: 'bar',
                responsive: true,
                data: {
                    labels: [],
                    datasets: [{
                        label: 'm3',
                        data: [],
                        backgroundColor: 'rgba(54, 162, 235, 0.2)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    scales: {
                        yAxes: [{
                            ticks: {
                                //beginAtZero: true
                            }
                        }]
                    }
                }
            });

            // Initial set-up
            runScript();

            window.setInterval(function () {
                runScript();
            }, chartInterval);

            function runScript() {
                updateCharts(1);
                updateCharts(3);

                // Update the last timestamp
                $("#lastUpdateTimestamp").text(moment().format('HH:mm'));
            }

            function updateCharts(deviceID) {
                $.ajax({
                    url: internalStaticInterval + "/" + deviceID,
                    dataType: 'json',
                    type: 'get',
                    contentType: 'application/json',
                    success: function (data, textStatus, jQxhr) {
                        let labelArray = [];
                        let amountArray = [];
                        if (data.deviceDetails.deviceType === 'electricity') {
                            $.each(data.measurements, function (index, value) {
                                labelArray.push(value.hour);
                                amountArray.push(value.amount);
                            });
                            energyChart.data.labels = labelArray;
                            energyChart.data.datasets[0].data = amountArray;

                            energyChart.update();
                        } else if (data.deviceDetails.deviceType === 'gas') {
                            $.each(data.measurements, function (index, value) {
                                labelArray.push(value.hour);
                                amountArray.push(value.amount);
                            });
                            gasChart.data.labels = labelArray;
                            gasChart.data.datasets[0].data = amountArray;

                            gasChart.update();
                        }
                    },
                    error: function (jqXhr, textStatus, errorThrown) {
                        console.log(textStatus);
                    }
                });
            }
        });
    </script>
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="box">
                <div class="box-header with-border">
                    <h3 class="box-title">Static view</h3>
                    <div class="pull-right" style="font-size: 12px;">
                        Laatst geüpdatet om <span id="lastUpdateTimestamp"></span>
                    </div>
                </div>
                <div class="box-body">
                    @if ($mobileDevice !== true)
                        <canvas id="energyChart"
                                width="{{ $defaultDimensions['width'] }}"
                                height="{{ $defaultDimensions['height'] }}"></canvas>
                        <canvas id="gasChart"
                                width="{{ $defaultDimensions['width'] }}"
                                height="{{ $defaultDimensions['height'] }}"></canvas>
                    @else
                        <canvas id="energyChart"></canvas>
                        <canvas id="gasChart"></canvas>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
