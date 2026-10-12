<table aria-describedby="reparaciones">
    <thead>
        <tr>
            <th style="background-color: #68cb82;color:#000000">Grado</th>
            <th style="background-color: #68cb82;color:#000000">Modelo</th>
            <th style="background-color: #68cb82;color:#000000">Memoria</th>
            <th style="background-color: #68cb82;color:#000000">Versión</th>
            <th style="background-color: #68cb82;color:#000000">Cantidad</th>
            <th style="background-color: #68cb82;color:#000000">Color</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($productos as $producto)
            <tr>
                <td>{{ $producto['grado'] }}</td>
                <td>{{ $producto['modelo'] }}</td>
                <td>{{ $producto['memoria'] }}</td>
                <td>{{ $producto['version'] }}</td>
                <td>{{ $producto['cantidad'] }}</td>
                <td>{{ $producto['colores_detalle'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
