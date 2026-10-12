<table aria-describedby="reparaciones">
    <thead>
        <tr>
            <th style="background-color: #68cb82;color:#000000">#</th>
            <th style="background-color: #68cb82;color:#000000">Tecnico</th>
            <th style="background-color: #68cb82;color:#000000">Producto</th>
            <th style="background-color: #68cb82;color:#000000">Costo Bs</th>
            <th style="background-color: #68cb82;color:#000000">Costo de Repuestos Bs</th>
            <th style="background-color: #68cb82;color:#000000">Costo Total Bs</th>
            <th style="background-color: #68cb82;color:#000000">Repuestos del Tecnico</th>
            <th style="background-color: #68cb82;color:#000000">Repuestos Propios</th>
            <th style="background-color: #68cb82;color:#000000">Repuestos a Devolver</th>
            <th style="background-color: #68cb82;color:#000000">Estado</th>
            <th style="background-color: #68cb82;color:#000000">Fecha Entrega</th>
            <th style="background-color: #68cb82;color:#000000">Fecha de Recogida</th>
            <th style="background-color: #68cb82;color:#000000">Pagado</th>
            <th style="background-color: #68cb82;color:#000000">Garantia del Tecnico</th>
            <th style="background-color: #68cb82;color:#000000">Venta ID de la garantia</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($reparaciones as $index => $reparacion)
            @php
                $producto = $reparacion->producto;
                $modelo = $producto->modelo;
            @endphp
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $reparacion->tecnico->nombre }}</td>
                <td>{{ $modelo->nombre }} {{ $producto->color }}, {{ $producto->bateria_porcentaje }}% -
                    {{ $producto->imei }}</td>
                <td>{{ $reparacion->costo }}</td>
                <td>{{ $reparacion->costo_repuestos }}</td>
                <td>{{ $reparacion->costo_total }}</td>
                <td>{{ $reparacion->repuestos_tecnico }}</td>
                <td>{{ $reparacion->repuestos_propios }}</td>
                <td>{{ $reparacion->repuestos_devolver }}</td>
                <td>{{ $reparacion->estado }}</td>
                <td>{{ $reparacion->fecha_entrega }}</td>
                <td>{{ $reparacion->fecha_recogida }}</td>
                <td>{{ $reparacion->pagado == 1 ? 'Si' : 'No' }}</td>
                <td>{{ $reparacion->garantia_tecnico }}</td>
                <td>{{ $reparacion->venta_id }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
