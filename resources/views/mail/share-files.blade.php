<p>Hello, <b class="capitalize">{{$user->name}}</b></p>

<p>User <b class="capitalize">{{ $author->name }}</b> has shared the following item{{count($files) > 1 ? 's' : ''}} with you:</p>
<hr>
<ol>
@foreach($files as $file)
    <li>{{$file->is_folder ? '<Folder>' : '<File>'}} {{$file->name}}</li>
@endforeach
</ol>
