@extends('errors.layout')

@section('code', '401')
@section('title', 'Sign in required')
@section('message', 'Your account must be signed in before you can open this page.')
@section('primary_url', route('login'))
@section('primary_label', 'Go to Sign In')
@section('tone', 'blue')
