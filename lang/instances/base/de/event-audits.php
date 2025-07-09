<?php

return [
  'unavailable_audits' => 'Es wurden keine Änderungen an dieser Veranstaltung vorgenommen!',
  'created' => [
    'metadata' => 'Am :audit_created_at, :user_name erstellte Datensatz<strong>:audit_url</strong>',
    'modified' => [
      'group' => '<strong>Ereignisgruppen-ID</strong>als \"<strong>:neu</strong>\" gesetzt',
      'event_date' => '<strong>Ereignisdatum</strong>als \"<strong>:neu</strong>\" gesetzt',
      'start' => '<strong>Ereignis-Startzeit</strong>als \"<strong>:new</strong>\" eingestellt',
      'end' => '<strong>Ereignis Endzeit</strong>als \"<strong>:new</strong>\" eingestellt',
      'venue' => '<strong>Ereignisname</strong>als \"<strong>:neu</strong>\" gesetzt',
      'location' => '<strong>Veranstaltungsort</strong>als \"<strong>:neu</strong>\" eingestellt',
      'latitude' => '<strong>Ereignis Breitengrad</strong>als \"<strong>:new</strong>\" gesetzt',
      'longitude' => '<strong>Ereignis Längengrad</strong>als \"<strong>:new</strong>\" gesetzt',
      'free_text' => '<strong>Freier Text</strong>gesetzt als \"<strong>:new</strong>\"',
      'pax' => '<strong>pax</strong>setze als \"<strong>:new</strong>\"',
      'volunteers' => '<strong>Anzahl der Freiwilligen</strong>als \"<strong>:neu</strong>\" eingestellt',
      'hours' => '<strong>Veranstaltungsstunden</strong>als \"<strong>:new</strong>\" eingestellt',
      'idevents' => '<strong>Ereignis-ID</strong>als \"<strong>:neu</strong>\" gesetzt',
      'wordpress_post_id' => '<strong>Wordpress Beitrag ID</strong>als \"<strong>:new</strong>\" eingestellt',
    ],
  ],
  'updated' => [
    'metadata' => 'Am :audit_created_at hat :user_name den Datensatz<strong>:audit_url</strong>aktualisiert',
    'modified' => [
      'group' => '<strong>Ereignisgruppen-ID</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'event_date' => '<strong>Ereignisdatum</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'start' => '<strong>Ereignis-Startzeit</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'end' => '<strong>Ereignis-Endzeit</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'venue' => '<strong>Ereignisname</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'location' => '<strong>Veranstaltungsort</strong>wurde geändert von \"<strong>:alt</strong>\" zu \"<strong>:neu</strong>\"',
      'latitude' => '<strong>Ereignis Breitengrad</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'longitude' => '<strong>Die Ereignislänge</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'free_text' => '<strong>Freier Text</strong>wurde geändert von \"<strong>:alt</strong>\" zu \"<strong>:neu</strong>\"',
      'pax' => '<strong>pax</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'volunteers' => '<strong>Anzahl der Freiwilligen</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'hours' => '<strong>Event Hours</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'wordpress_post_id' => '<strong>Wordpress Beitrag ID</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
      'devices_updated_at' => '<strong>Ereignis Geräte aktualisiert am</strong>wurde von \"<strong>:alt</strong>\" auf \"<strong>:neu</strong>\" geändert',
    ],
  ],
];
