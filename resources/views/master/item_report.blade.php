<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bootstrap demo</title>
    <link href="{{ url('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
</head>

<body>
    <div class="container-fluid">
        <h1>Hello, world!</h1>
        <div class="mb-3 row">
            <label for="staticEmail" class="col-sm-2 col-form-label">Email</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="staticEmail" value="email@example.com">
            </div>
        </div>
        <div class="mb-3 row">
            <label for="inputPassword" class="col-sm-2 col-form-label">Name</label>
            <div class="col-sm-10">
                <input type="text" class="form-control" id="inputName">
            </div>
        </div>
        <div class="mb-3 row">
            <label for="inputPassword" class="col-sm-2 col-form-label"></label>
            <div class="col-sm-10">
                <div class="btn btn-primary" onclick="BtnViewOnClick(this)">View</div>
            </div>
        </div>
        <div class="row border-top">
            <iframe id="frame1" src="report/PDF" frameborder="0" height="300px"></iframe>
        </div>
    </div>

    <script src="{{ url('assets/bootstrap/js/bootstrap.min.js') }}"></script>
    <script>
        function BtnViewOnClick(senderElement) {
            senderElement.innerHTML = 'Please wait'
            frame1.src = `report/PDF?name=${inputName.value}`
            senderElement.innerHTML = 'View'
        }
    </script>
</body>

</html>