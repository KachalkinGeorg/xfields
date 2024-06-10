<form method="post" action="">
<div class="panel panel-default">
	<div class="panel-heading">{{panel}}
		<div class="panel-head-right">{{header}}</div>
	</div>
	<div class="table-responsive">
		<table class="table table-striped">
		<tr>
			<td class="col-xs-6 col-sm-6 col-md-7">
				<h6 class="media-heading text-semibold">Чпу включен?:</h6>
				<span class="text-muted text-size-small hidden-xs">При включении - значения доп. полей будут выводится в виде ссылок на показ других публикаций, которые имеют такие же значения.</span>
			</td>
			<td class="col-xs-6 col-sm-6 col-md-5">
				<select name="url" >{{info}}</select>
			</td>
		</tr>
		</table>
	</div>
	<div class="panel-footer" align="center">
		<button type="submit" name="submit" class="btn btn-outline-primary">сохранить</button>	
	</div>
	
</div>

</form>