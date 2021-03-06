@extends('mainLayout')
@section('content')
    <script type="text/javascript">
        const chartInterval = {{ $refreshInterval }};
        const rangeType = "{{ $rangeType }}";
        const internalBudgetAPI = "{{ url('meter/api/getBudget/') }}";

        $(document).ready(function () {
            let budgetChart = new Chart($("#budgetChart"), {
                type: 'pie',
                responsive: true,
                data: {
                    labels: [],
                    datasets: [{
                        label: null,
                        data: [],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)'
                        ],
                        borderColor: [
                            'rgba(255,99,132,1)',
                            'rgba(54, 162, 235, 1)'
                        ],
                        borderWidth: 1
                    }]
                }
            });

            // Initial set-up
            runScript();
            $("#btnRange_" + rangeType).removeClass('btn-default').addClass('btn-primary');

            window.setInterval(function () {
                runScript();
            }, chartInterval);


            function runScript() {
                updateCharts();

                // Update the last timestamp
                $("#lastUpdateTimestamp").text(moment().format('HH:mm'));
            }

            function updateCharts() {
                $.ajax({
                    url: internalBudgetAPI + "/" + rangeType,
                    dataType: 'json',
                    type: 'get',
                    contentType: 'application/json',
                    success: function (data, textStatus, jQxhr) {
                        console.log(data);
                        let labelArray = [];
                        let amountArray = [];

                        $.each(data.devices, function (index, value) {
                            amountArray.push(value.amount);
                            labelArray.push(value.description);
                        });

                        budgetChart.data.labels = labelArray;
                        budgetChart.data.datasets[0].data = amountArray;
                        budgetChart.update();

                        let progressBar = $("#budgetProcessBar");
                        let infoBox = $("#budgetInfoBox");
                        let cssProgressPercentage = 100;

                        if (data.budgetPercentage < 100) {
                            cssProgressPercentage = data.budgetPercentage;
                        }

                        $("#budgetAllowed").text(data.budgetCurrency + " " + data.budgetAllowed);
                        $("#budgetSpent").text(data.budgetCurrency + " " + data.budgetSpent);
                        $("#budgetPercentage").text(data.budgetPercentage + "%");

                        infoBox.addClass(data.infoBoxClass);
                        progressBar.css('width', cssProgressPercentage + "%");
                        progressBar.text(data.budgetCurrency + " " + data.budgetSpent + " / " + data.budgetCurrency + " " + data.budgetAllowed);
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
                    <h3 class="box-title">Budgetoverzicht</h3>
                    <div class="pull-right" style="font-size: 12px;">
                        Laatst geüpdatet om <span id="lastUpdateTimestamp"></span>
                    </div>
                </div>
                <div class="box-body">
                    <div class="info-box" id="budgetInfoBox">
                        <span class="info-box-icon">
                            <span id="budgetPercentage" style="font-size: 36px;"></span>
                        </span>
                        <div class="info-box-content">
                            <span class="info-box-text">
                                Gebudgeteerd: <span id="budgetAllowed"></span>
                            </span>
                            <span class="info-box-number">
                                Gespendeerd: <span id="budgetSpent"></span>
                            </span>
                            <div class="progress">
                                <div class="progress-bar" id="budgetProcessBar"></div>
                            </div>
                            <span class="progress-description"></span>
                        </div>
                    </div>
                    <p>&nbsp;</p>
                    @if ($mobileDevice !== true)
                        <canvas id="budgetChart"
                                width="{{ $defaultDimensions['width'] }}"
                                height="{{ $defaultDimensions['height'] }}"></canvas>
                    @else
                        <canvas id="budgetChart"></canvas>
                    @endif
                </div>
                <div class="box-footer with-border">
                    <div class="text-center">
                        <div class="btn-group">
                            <a href="{{ url('meter/budget/d') }}" class="btn btn-default" id="btnRange_d">Dag</a>
                        <!--<a href="{{ url('meter/budget/w') }}" class="btn btn-default" id="btnRange_w">Week</a>!-->
                            <a href="{{ url('meter/budget/m') }}" class="btn btn-default" id="btnRange_m">Maand</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
