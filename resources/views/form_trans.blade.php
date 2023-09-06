<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transport</title>
    <link href="{{ url('assets/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
</head>

<body>
    <h1>Transport</h1>
    <div class="container">
        <div class="row">
            <div class="col">
                <div class="mb-1">
                    <label for="txtSearch" class="form-label">Search</label>
                    <input type="text" class="form-control" id="txtSearch" onkeypress="searchItem(event)" placeholder="search something">
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col">
                <div class="table-responsive" id="tabelItemContainer">
                    <table class="table" id="tabelItem">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Plat Number</th>
                                <th scope="col">Trans Type</th>
                                <th scope="col">Date</th>
                                <th scope="col">User ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="{{ url('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    <script src="{{ url('assets/jquery/jquery.min.js') }}"></script>
</body>
<script>
    function searchItem(e) {
        if (e.key === 'Enter') {
            const data = {
                transName: txtSearch.value
            }
            $.ajax({
                type: "GET",
                url: "trans",
                data: data,
                dataType: "json",
                success: function(response) {
                    console.log(response)
                    let myContainer = document.getElementById("tabelItemContainer");
                    let myfrag = document.createDocumentFragment();
                    let cln = tabelItem.cloneNode(true);
                    myfrag.appendChild(cln);
                    let myTable = myfrag.getElementById("tabelItem");
                    let myTableBody = myTable.getElementsByTagName("tbody")[0];
                    myTableBody.innerHTML = ''
                    let nomorUrut = 1
                    response.data.forEach((arrayItem) => {
                        newrow = myTableBody.insertRow(-1)
                        newcell = newrow.insertCell(0)
                        newcell.innerText = nomorUrut
                        newcell = newrow.insertCell(1)
                        newcell.innerText = arrayItem['MSTTRANS_ID']
                        newcell = newrow.insertCell(2)
                        newcell.innerText = arrayItem['MSTTRANS_TYPE']
                        newcell = newrow.insertCell(3)
                        newcell.innerText = arrayItem['MSTTRANS_LUPDT']
                        newcell = newrow.insertCell(4)
                        newcell.innerText = arrayItem['MSTTRANS_USRID']
                        nomorUrut++
                    })
                    myContainer.innerHTML = ''
                    myContainer.appendChild(myfrag)
                }
            });
        }
    }
</script>

</html>