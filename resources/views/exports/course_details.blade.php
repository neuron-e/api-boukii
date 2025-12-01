<table>
    <thead>
    <tr>
        <th>{{ __('messages.Curso') }}</th>
        <th>{{ __('messages.Fecha') }}</th>
        <th>{{ __('messages.Horario') }}</th>
        <th>{{ __('messages.Grupo (Nivel)') }}</th>
        <th>{{ __('messages.Subgrupo') }}</th>
        <th>{{ __('messages.Monitor') }}</th>
        <th>{{ __('messages.Nombre del Alumno') }}</th>
        <th>{{ __('messages.Edad') }}</th>
        <th>{{ __('messages.Contacto') }}</th>
        <th>{{ __('messages.Estado de Pago') }}</th>
        <th>{{ __('messages.Forfait Commandé') }}</th>
        <th>Email</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <!-- Agrupar por Fecha -->
        <td colspan="11"><strong>{{ $course->name }}</strong></td>
    </tr>
    @if($course->course_type === 2) <!-- Cursos privados -->
        @foreach ($course->courseDates as $date)
            @foreach ($date->bookingUsers as $bookingUser)
                <tr>
                    <td>{{ $course->name }}</td>
                    <td>{{ $date->date }}</td>
                    <td>{{ $date->hour_start }} - {{ $date->hour_end }}</td>
                    <td>{{ __('messages.Privado') }}</td>
                    <td>-</td>
                    <td>{{ $bookingUser->monitor->fullname ?? __('messages.Sin asignar') }}</td>
                    <td>{{ $bookingUser->client->fullname ?? '' }}</td>
                    @php
                        $birthDate = $bookingUser->client->birth_date ?? null;
                        $age = $birthDate ? \Carbon\Carbon::parse($birthDate)->age : null;
                    @endphp
                    <td>{{ $age ?? 'N/A' }}</td>
                    <td>{{ optional($bookingUser->booking->clientMain ?? null)->phone ?? '' }}</td>
                    <td>{{ optional($bookingUser->booking ?? null)->paid ? __('messages.Efectuado') : __('messages.Por hacer') }}</td>
                    <td>
                        @php
                            $extras = collect($bookingUser->bookingUserExtras ?? []);
                        @endphp
                        @if($extras->count())
                            {{ implode(', ', $extras->map(fn($ex) => $ex->courseExtra->description ?? '')->filter()->toArray()) }}
                        @else
                            {{ __('messages.No seleccionado') }}
                        @endif
                    </td>
                    <td>{{ $bookingUser->client->email ?? '' }}</td>
                </tr>
            @endforeach
        @endforeach
    @endif
    @if($course->course_type === 1) <!-- Cursos privados -->
    @foreach ($course->courseDates as $date)
        <tr>
            <!-- Agrupar por Fecha -->
            <td colspan="11"><strong>Fecha: {{ $date->date }}</strong></td>
        </tr>
        @foreach ($date->courseGroups as $group)
            <tr>
                <!-- Agrupar por Grupo (Nivel) -->
                <td></td>
                <td></td>
                <td></td>
                <td colspan="8"><strong>{{__('messages.Grupo (Nivel)')}} {{ $group->degree->name }}</strong></td>
            </tr>
            @foreach ($group->courseSubgroups as $subgroup)
                <tr>
                    <!-- Agrupar por Subgrupo -->
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td colspan="7"><strong>{{__('messages.Subgrupo:')}} {{ $loop->iteration }}</strong></td>
                </tr>
                @foreach ($subgroup->bookingUsers as $bookingUser)
                    <tr>
                        <td></td>
                        <td>{{ $date->date }}</td>
                        <td>{{ $date->hour_start }} - {{ $date->hour_end }}</td>
                        <td>{{ $group->degree->name }}</td>
                        <td>{{ $subgroup->id }}</td>
                        <td>{{ $subgroup->monitor->fullname ?? __('messages.Sin asignar') }}</td>
                        <td>{{ $bookingUser->client->fullname }}</td>
                        <td>{{ \Carbon\Carbon::parse($bookingUser->client->birth_date)->age ?? 'N/A' }}</td>
                        <td>{{ $bookingUser->booking->clientMain->phone }}</td>
                        <td>{{ $bookingUser->booking->paid ? __('messages.Efectuado') : __('messages.Por hacer') }}</td>
                        <td>{{ count($bookingUser->bookingUserExtras) ? $bookingUser->bookingUserExtras[0]->courseExtra->description : __('messages.No seleccionado') }}</td>
                        <td>{{ $bookingUser->client->email}}</td>
                    </tr>
                @endforeach
            @endforeach
        @endforeach
    @endforeach
    @endif
    </tbody>
</table>
