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
                <div class="mb-3">
                    <label for="txtSearch" class="form-label">Search</label>
                    <input type="email" class="form-control" id="txtSearch" onkeypress="searchItem(event)" placeholder="search something">
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
                                <th scope="col">Item Code</th>
                                <th scope="col">Description</th>
                            </tr>
                        </thead>
                        <tbody>
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
                itemName: txtSearch.value
            }
            $.ajax({
                type: "GET",
                url: "items",
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
                        newcell.innerText = arrayItem['MITM_ITMCD']
                        newcell = newrow.insertCell(2)
                        newcell.innerText = arrayItem['MITM_ITMD1']
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