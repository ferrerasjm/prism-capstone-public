@extends('errors.layout')

@section('code', '503')
@section('title', 'Temporarily unavailable')
@section('message', 'PRISM is currently undergoing maintenance or is temporarily unable to accept requests.')
@section('primary_url', request()->fullUrl())
@section('primary_label', 'Try Again')
@section('tone', 'orange')
@section('help', 'Please wait a few minutes and try again. No action is required on your account.')
