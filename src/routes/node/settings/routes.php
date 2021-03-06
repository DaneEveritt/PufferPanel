<?php
/*
	PufferPanel - A Game Server Management Panel
	Copyright (c) 2015 Dane Everitt

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see http://www.gnu.org/licenses/.
*/
namespace PufferPanel\Core;
use \ORM, \Unirest, \Tracy;

$klein->respond('GET', '/node/settings', function($request, $response, $service) use ($core) {

	if(!$core->permissions->has('manage.view')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	$response->body($core->twig->render('node/settings.html', array(
		'flash' => $service->flashes(),
		'xsrf' => $core->auth->XSRF(),
		'server' => array_merge($core->server->getData(), array('server_jar' => (str_replace(".jar", "", $core->server->getData('server_jar'))))),
		'node' => $core->server->nodeData()
	)))->send();

});

$klein->respond('POST', '/node/settings/ftp', function($request, $response, $service) use ($core) {

	if(!$core->permissions->has('manage.ftp.password')) {

		$response->code(403);
		$response->body($core->twig->render('node/403.html'))->send();
		return;

	}

	if(!$core->auth->XSRF($request->param('xsrf'))) {

		$service->flash('<div class="alert alert-warning"> The XSRF token recieved was not valid. Please make sure cookies are enabled and try your request again.</div>');
		$response->redirect('/node/settings?tab=ftp_sett')->send();

	}

	if(!$core->auth->validatePasswordRequirements($request->param('sftp_pass'))) {

		$service->flash('<div class="alert alert-danger">The password you provided does not meet the requirements. Please use at least 8 characters, include at least one number, and use mixed case.</div>');
		$response->redirect('/node/settings?tab=sftp_sett')->send();

	} else {

		/*
		 * Update Server FTP Information
		 */
		try {

			$unirest = Unirest\Request::post(
				'https://'.$core->server->nodeData('fqdn').':'.$core->server->nodeData('daemon_listen').'/server/reset-password',
				array(
					"X-Access-Token" => $core->server->nodeData('daemon_secret'),
					"X-Access-Server" => $core->server->getData('hash')
				),
				array(
					"password" => $request->param('sftp_pass')
				)
			);

			if($unirest->code === 204) {
				$service->flash('<div class="alert alert-success">Your SFTP password has been updated.</div>');
				$response->redirect('/node/settings?tab=sftp_sett')->send();
			} else {
				throw new \Exception("pufferd did not return a success code while attempting to reset an account password. (code: ".$unirest->code.")");
			}

		} catch(\Exception $e) {

			Tracy\Debugger::log($e);
			$service->flash('<div class="alert alert-danger">Unable to access the pufferd daemon to reset your password. Please try again in a moment.</div>');
			$response->redirect('/node/settings?tab=sftp_sett')->send();

		}

	}

});

$klein->respond('POST', '/node/settings/password', function($request, $response) use ($core) {

	$response->body($core->auth->keygen(rand(6, 10))."-".$core->auth->keygen(rand(6, 14)))->send();

});