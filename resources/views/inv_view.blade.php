<html>

<table class="table">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory</title>
    <link href="{{ url('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
</head>
                    <thead>
                   <div class="row justify-content-left">
                    <div class="col-md-12">
                        <br />
                        <div class="card">
                            <div class="card-header bgsize-primary-4 white card-header">
                                <h4 class="card-title">WMS Inventory</h4>
                            </div>
                           
                            
                                
                                <div class=" card-content table-responsive">
                               
                                    <table id="example" class="table table-striped table-bordered" style="width:100%">
                                    <a href="{{url("export")}}" class="btn btn-primary" style="margin-left:85%">Export Excel Data</a>
                                        <thead>
                                            <th scope="col">No.</th>
                                            <th scope="col">Location</th>
                                            <th scope="col">Part Code</th>
                                            <th scope="col">Part Name</th>
                                            <th scope="col">QTY</th>
                                            <th scope="col">Box</th>
                                            <th scope="col">Total</th>
                                            <th scope="col">Checked By</th>
                                            <th scope="col">Auditor</th>
                                        </thead>
                                        <tbody>
                                        @php $no = 1 @endphp
                                  
                                        @php ($loc = null) @endphp
                                            @foreach ($Inv as $inv)
                                        
                                                @if ($loop->index > 0 && $loc != $inv->cLoc)
                                                    @include('subtotal', compact('inv', 'loc'))
                                                    @endif
                                                    <tr>
                                                        @if ($loc == $inv->cLoc)
                                                        <td colspan="2"></td>
                                                        @else 
                                                    @php ($loc = $inv->cLoc) @endphp
                                                    <td>{{ $no++ }}</td>
                                                    <td>{{ $inv->cLoc}}</td>
                                                    @endif
                                                    <td>{{ $inv->cAssyNo }}</td>
                                                    <td>{{ $inv->cModel }}</td>
                                                    <td>{{ $inv->cQty }}</td>
                                                    <td>{{ $inv->BOX }}</td>
                                                    <td>{{ $inv->Total }}</td>
                                                    @if ($loop->last)
                                                       @include ('subtotal', compact('inv', 'loc'))
                                                    @endif
                                                @endforeach
                                       
                                       
                                        </tbody>
                                    </table>
                                    {!! $Inv->links('pagination::bootstrap-4') !!}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
   <script type="text/javascript" src="https://code.jquery.com/jquery-3.5.1.js"></script>
   <script>
       $(document).ready(function() {
           $('#example').DataTable();
       } );
   </script>

</body>

</html>