<html>
    
<tr>
    <td colspan="4"></td>
    <td>TOTAL</td>
    <td>{{ $Inv->where('cLoc', $loc)->sum('BOX') }}</td>
    <td>{{ $Inv->where('cLoc', $loc)->sum('Total') }}</td>
</tr>
</tr>
<tr>
    <td colspan="8"></td>
</tr>
</html>