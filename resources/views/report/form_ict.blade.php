<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ICT Form</title>
    <link href="{{ url('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
    <link href="{{ url('assets/bootstrap_dp/css/bootstrap-datepicker.min.css') }}" rel="stylesheet">

    <script type="text/javascript" src="{{ url('assets/jquery/jquery.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('assets/bootstrap_dp/js/bootstrap-datepicker.min.js') }}"></script>
</head>

<body>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <h1>Report</h1>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="formId" class="form-label">Form</label>
                <select class="form-select" id="formId">
                    @foreach ($trackers as $r)
                    <option value="{{ $r->id }}">{{$r->name}}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6 mb-3">
                <label for="statusId" class="form-label">Status</label>
                <select class="form-select" id="statusId">
                    <option value="-">All</option>
                    <option value="1">Open</option>
                    <option value="0">Closed</option>
                </select>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="dateFrom" class="form-label">Date from</label>
                <input type="text" class="form-control" id="dateFrom" readonly>
            </div>
            <div class="col-md-6 mb-3">
                <label for="dateFrom" class="form-label">Date to</label>
                <input type="text" class="form-control" id="dateTo" readonly>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <button class="btn btn-success" onclick="btnExportOnClick(this)">Export to Spreadsheet</button>
            </div>
        </div>
        <div class="row">
            <div class="col" id="div_alert">

            </div>
        </div>
    </div>
    <script src="{{ url('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script type="text/javascript" src="{{ url('assets/js/FileSaver.js') }}"></script>
    <script>
        $("#dateFrom").datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            clearBtn: true
        });
        $("#dateTo").datepicker({
            format: 'yyyy-mm-dd',
            autoclose: true,
            clearBtn: true
        });

        function btnExportOnClick(p) {
            p.disabled = true
            $.ajax({
                type: "GET",
                url: "redmine/export-issue",
                data: {
                    outputType: 'XLSX',
                    dateFrom: dateFrom.value,
                    dateTo: dateTo.value,
                    tracker_id: formId.value,
                    statusId: statusId.value
                },
                success: function(response) {
                    p.disabled = false
                    const blob = new Blob([response], {
                        type: "application/vnd.ms-excel"
                    })

                    const selectedFormText = $("#formId option:selected").text()
                    const fileName = `${selectedFormText}.xlsx`
                    saveAs(blob, fileName)
                    
                },
                xhr: function() {
                    const xhr = new XMLHttpRequest()
                    xhr.onreadystatechange = function() {
                        if (xhr.readyState == 2) {
                            if (xhr.status == 200) {
                                xhr.responseType = "blob";
                            } else {
                                p.disabled = false
                                xhr.responseType = "text";
                            }
                        }
                    }
                    return xhr
                },
                error: function(xhr, xopt, xthrow) {
                    p.disabled = false
                    const respon = Object.keys(xhr.responseJSON)
                    let msg = ''
                    for (const item of respon) {
                        msg += `<p>${xhr.responseJSON[item]}</p>`
                    }
                    div_alert.innerHTML = `<div class="alert alert-warning alert-dismissible fade show" role="alert">
                    ${msg}
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    </div>`
                }
            })
        }
    </script>
</body>

</html>