<table class="table">
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
                        @foreach($Trans as $trans)
                        <tr>
                        <td></td>
                        <td>{{ $trans->MSTTRANS_ID }}</td>
                        <td>{{ $trans->MSTTRANS_TYPE }}</td>
                        <td>{{ $trans->MSTTRANS_LUPDT }}</td>
                        <td>{{ $trans->MSTTRANS_USRID }}</td>
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