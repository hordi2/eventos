{{--
    Repli des erreurs 4xx sans page dédiée (409, 410...) : sans elle, Laravel
    affiche en production une page générique en anglais et perd le message
    français passé à abort() (par exemple une invitation expirée).
--}}
@extends('errors::minimal')

@section('title', __('Requête impossible'))
@section('code', $exception->getStatusCode())
@section('message', __($exception->getMessage() ?: 'Cette demande ne peut pas aboutir.'))
