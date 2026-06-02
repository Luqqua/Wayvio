<!DOCTYPE html>
@include('layouts.lang')
<head>
   <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
   @stack('wayvio-head')
   @stack('wayvio-head-end')
</head>
<body>
   @stack('wayvio-body-start')
   <div class="container">
      <div class="row">
         <div class="column" style="margin-top: 5%">
            @stack('wayvio-content')
         </div>
      </div>
   </div>
   @stack('wayvio-body-end')
   @include('layouts.autofill-strict-off')
</body>
</html>
