<p>Hello, <b>{{$user->name}}</b></p>

<p>User <b>{{ $author->name }}</b> has shared the following item{{count($files) > 1 ? 's' : ''}} with you:</p>
<hr>
<ol>
@foreach($files as $file)
    <li>{{$file->is_folder ? 'Folder' : 'File'}} - {{$file->name}}</li>
@endforeach
</ol>
