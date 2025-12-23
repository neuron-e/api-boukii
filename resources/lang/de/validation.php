<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => 'Das Feld :attribute muss akzeptiert werden.',
    'active_url' => 'Das Feld :attribute ist keine gültige URL.',
    'after' => 'Das Feld :attribute muss ein Datum nach dem :date sein.',
    'after_or_equal' => 'Das Feld :attribute muss ein Datum nach dem :date oder gleich dem :date sein.',
    'alpha' => 'Das Feld :attribute darf nur Buchstaben enthalten.',
    'alpha_dash' => 'Das Feld :attribute darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.',
    'alpha_num' => 'Das Feld :attribute darf nur Buchstaben und Zahlen enthalten.',
    'array' => 'Das Feld :attribute muss ein Array sein.',
    'before' => 'Das Feld :attribute muss ein Datum vor dem :date sein.',
    'before_or_equal' => 'Das Feld :attribute muss ein Datum vor dem :date oder gleich dem :date sein.',
    'between' => [
        'numeric' => 'Das Feld :attribute muss zwischen :min und :max liegen.',
        'file' => 'Das Feld :attribute muss zwischen :min und :max Kilobytes groß sein.',
        'string' => 'Das Feld :attribute muss zwischen :min und :max Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss zwischen :min und :max Elemente haben.',
    ],
    'boolean' => 'Das Feld :attribute muss wahr oder falsch sein.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'date' => 'Das Feld :attribute ist kein gültiges Datum.',
    'date_equals' => 'Das Feld :attribute muss ein Datum gleich :date sein.',
    'date_format' => 'Das Feld :attribute entspricht nicht dem Format :format.',
    'different' => 'Die Felder :attribute und :other müssen sich unterscheiden.',
    'digits' => 'Das Feld :attribute muss :digits Stellen haben.',
    'digits_between' => 'Das Feld :attribute muss zwischen :min und :max Stellen haben.',
    'dimensions' => 'Das Feld :attribute hat ungültige Bildabmessungen.',
    'distinct' => 'Das Feld :attribute hat einen doppelten Wert.',
    'email' => 'Das Feld :attribute muss eine gültige E-Mail-Adresse sein.',
    'ends_with' => 'Das Feld :attribute muss mit einem der folgenden Werte enden: :values.',
    'exists' => 'Das ausgewählte :attribute ist ungültig.',
    'file' => 'Das Feld :attribute muss eine Datei sein.',
    'filled' => 'Das Feld :attribute muss einen Wert haben.',
    'gt' => [
        'numeric' => 'Das Feld :attribute muss größer als :value sein.',
        'file' => 'Das Feld :attribute muss größer als :value Kilobytes sein.',
        'string' => 'Das Feld :attribute muss mehr als :value Zeichen haben.',
        'array' => 'Das Feld :attribute muss mehr als :value Elemente haben.',
    ],
    'gte' => [
        'numeric' => 'Das Feld :attribute muss größer oder gleich :value sein.',
        'file' => 'Das Feld :attribute muss größer oder gleich :value Kilobytes sein.',
        'string' => 'Das Feld :attribute muss mindestens :value Zeichen haben.',
        'array' => 'Das Feld :attribute muss mindestens :value Elemente haben.',
    ],
    'image' => 'Das Feld :attribute muss ein Bild sein.',
    'in' => 'Das ausgewählte :attribute ist ungültig.',
    'in_array' => 'Das Feld :attribute existiert nicht in :other.',
    'integer' => 'Das Feld :attribute muss eine ganze Zahl sein.',
    'ip' => 'Das Feld :attribute muss eine gültige IP-Adresse sein.',
    'ipv4' => 'Das Feld :attribute muss eine gültige IPv4-Adresse sein.',
    'ipv6' => 'Das Feld :attribute muss eine gültige IPv6-Adresse sein.',
    'json' => 'Das Feld :attribute muss ein gültiger JSON-String sein.',
    'lt' => [
        'numeric' => 'Das Feld :attribute muss kleiner als :value sein.',
        'file' => 'Das Feld :attribute muss kleiner als :value Kilobytes sein.',
        'string' => 'Das Feld :attribute muss weniger als :value Zeichen haben.',
        'array' => 'Das Feld :attribute muss weniger als :value Elemente haben.',
    ],
    'lte' => [
        'numeric' => 'Das Feld :attribute muss kleiner oder gleich :value sein.',
        'file' => 'Das Feld :attribute muss kleiner oder gleich :value Kilobytes sein.',
        'string' => 'Das Feld :attribute darf höchstens :value Zeichen haben.',
        'array' => 'Das Feld :attribute darf nicht mehr als :value Elemente haben.',
    ],
    'max' => [
        'numeric' => 'Das Feld :attribute darf nicht größer als :max sein.',
        'file' => 'Das Feld :attribute darf nicht größer als :max Kilobytes sein.',
        'string' => 'Das Feld :attribute darf nicht mehr als :max Zeichen haben.',
        'array' => 'Das Feld :attribute darf nicht mehr als :max Elemente haben.',
    ],
    'mimes' => 'Das Feld :attribute muss eine Datei vom Typ :values sein.',
    'mimetypes' => 'Das Feld :attribute muss eine Datei vom Typ :values sein.',
    'min' => [
        'numeric' => 'Das Feld :attribute muss mindestens :min sein.',
        'file' => 'Das Feld :attribute muss mindestens :min Kilobytes groß sein.',
        'string' => 'Das Feld :attribute muss mindestens :min Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss mindestens :min Elemente haben.',
    ],
    'not_in' => 'Das ausgewählte :attribute ist ungültig.',
    'not_regex' => 'Das Format von :attribute ist ungültig.',
    'numeric' => 'Das Feld :attribute muss eine Zahl sein.',
    'password' => 'Das Passwort ist falsch.',
    'phone' => 'Das Feld :attribute enthält eine ungültige Nummer.',
    'present' => 'Das Feld :attribute muss vorhanden sein.',
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => 'Das Feld :attribute ist erforderlich.',
    'required_if' => 'Das Feld :attribute ist erforderlich, wenn :other :value ist.',
    'required_unless' => 'Das Feld :attribute ist erforderlich, es sei denn, :other ist in :values.',
    'required_with' => 'Das Feld :attribute ist erforderlich, wenn :values vorhanden ist.',
    'required_with_all' => 'Das Feld :attribute ist erforderlich, wenn :values vorhanden sind.',
    'required_without' => 'Das Feld :attribute ist erforderlich, wenn :values nicht vorhanden ist.',
    'required_without_all' => 'Das Feld :attribute ist erforderlich, wenn keine der :values vorhanden sind.',
    'same' => 'Die Felder :attribute und :other müssen übereinstimmen.',
    'size' => [
        'numeric' => 'Das Feld :attribute muss :size sein.',
        'file' => 'Das Feld :attribute muss :size Kilobytes groß sein.',
        'string' => 'Das Feld :attribute muss :size Zeichen lang sein.',
        'array' => 'Das Feld :attribute muss :size Elemente enthalten.',
    ],
    'starts_with' => 'Das Feld :attribute muss mit einem der folgenden Werte beginnen: :values.',
    'string' => 'Das Feld :attribute muss eine Zeichenkette sein.',
    'timezone' => 'Das Feld :attribute muss eine gültige Zeitzone sein.',
    'unique' => 'Das Feld :attribute ist bereits vergeben.',
    'uploaded' => 'Das Hochladen von :attribute ist fehlgeschlagen.',
    'url' => 'Das Format von :attribute ist ungültig.',
    'uuid' => 'Das Feld :attribute muss eine gültige UUID sein.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
