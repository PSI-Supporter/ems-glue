<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Item List</title>
    <link href="{{ url('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
</head>

<body>
    <h1>Items</h1>
    <div class="container">
        <div class="row">
            <div class="col">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Item Code</th>
                            <th scope="col">Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                        <tr>
                        <td></td>
                        <td>{{ $item->MITM_ITMCD }}</td>
                        <td>{{ $item->MITM_ITMD1 }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script src="{{ url('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
</body>

</html>