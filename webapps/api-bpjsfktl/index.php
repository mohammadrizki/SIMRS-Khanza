<?php
    require_once ('conf.php');
    header("Access-Control-Allow-Origin: *");
    header("Content-Type: application/json");
    header("Access-Control-Allow-Methods: POST, GET");
    header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
    $url    = isset($_GET['url']) ? $_GET['url'] : '/';
    $url    = explode("/", $url);
    $header = apache_request_headers();
    $method = $_SERVER['REQUEST_METHOD'];
    $waktutunggu=10;
    
    if (($method == 'GET') && (!empty($header['x-username'])) && (!empty($header['x-password']))) {
        $hash_user = hash_pass($header['x-username'], 12);
        $hash_pass = hash_pass($header['x-password'], 12);
        if($url[0]=="auth"){
            $response=createtoken($header['x-username'],$header['x-password']);
        }else{
            $response = array(
                'metadata' => array(
                    'message' => 'Service tidak terdaftar',
                    'code' => 201
                )
            );
            http_response_code(201);
        }
    }
  
    if (($method == 'POST') && (!empty($header['x-username'])) && (!empty($header['x-password']))) {
        $hash_user = hash_pass($header['x-username'], 12);
        switch ($url[0]) {
            case "statusantrean":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if(empty($decode['kodepoli'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Poli tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodepoli'],"'")||strpos($decode['kodepoli'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Poli tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(empty($decode['kodedokter'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Dokter tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodedokter'],"'")||strpos($decode['kodedokter'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Dokter tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(empty($decode['tanggalperiksa'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$decode['tanggalperiksa'])){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Tanggal tidak sesuai, format yang benar adalah yyyy-mm-dd',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(empty($decode['jampraktek'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jam Praktek tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['jampraktek'],"'")||strpos($decode['jampraktek'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jam Praktek tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else{
                        $kdpoli     = getOne2("SELECT kd_poli_rs FROM maping_poli_bpjs WHERE kd_poli_bpjs='$decode[kodepoli]'");
                        $kddokter   = getOne2("SELECT kd_dokter FROM maping_dokter_dpjpvclaim WHERE kd_dokter_bpjs='$decode[kodedokter]'");
                        $hari       = strtoupper(hariindo($decode['tanggalperiksa']));
                        if(empty($kdpoli)) { 
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Poli tidak ditemukan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else if(empty($kddokter)) { 
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Dokter tidak ditemukan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else{
                            $jammulai   = substr($decode['jampraktek'],0,5);
                            $jamselesai = substr($decode['jampraktek'],6,5);
                            $kuota      = getOne2("select kuota from jadwal where hari_kerja='$hari' and kd_dokter='$kddokter' and kd_poli='$kdpoli' and jam_mulai='$jammulai:00' and jam_selesai='$jamselesai:00'");
                            
                            if(empty($kuota)) {
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Pendaftaran ke Poli ini sedang tutup',
                                        'code' => 201
                                    )
                                );
                                http_response_code(201);
                            }else{
                                $data = fetch_array(bukaquery2("SELECT poliklinik.nm_poli,COUNT(reg_periksa.kd_poli) as total_antrean,dokter.nm_dokter,
                                    CONCAT(00,COUNT(reg_periksa.kd_poli)) as antrean_panggil,SUM(CASE WHEN reg_periksa.stts!='Sudah' THEN 1 ELSE 0 END) as sisa_antrean,
                                    ('Datanglah Minimal 30 Menit, jika no antrian anda terlewat, silakan konfirmasi ke bagian Pendaftaran atau Perawat Poli, Terima Kasih ..') as keterangan
                                    FROM reg_periksa INNER JOIN poliklinik ON poliklinik.kd_poli=reg_periksa.kd_poli INNER JOIN dokter ON reg_periksa.kd_dokter=dokter.kd_dokter
                                    WHERE reg_periksa.tgl_registrasi='$decode[tanggalperiksa]' AND reg_periksa.kd_poli='$kdpoli' and reg_periksa.kd_dokter='$kddokter' 
                                    and jam_reg between '$jammulai:00' and '$jamselesai:00'"));
                                
                                if ($data['sisa_antrean'] >0) {
                                    $response = array(
                                        'response' => array(
                                            'namapoli' => $data['nm_poli'],
                                            'namadokter' => $data['nm_dokter'],
                                            'totalantrean' => $data['total_antrean'],
                                            'sisaantrean' => $data['sisa_antrean'],
                                            'antreanpanggil' =>$kdpoli."-".$data['antrean_panggil'],
                                            'sisakuotajkn' => ($kuota-$data['total_antrean'])."",
                                            'kuotajkn' => $kuota,
                                            'sisakuotanonjkn' => ($kuota-$data['total_antrean'])."",
                                            'kuotanonjkn' => ($kuota),
                                            'keterangan' => $data['keterangan']
                                        ),
                                        'metadata' => array(
                                            'message' => 'Ok',
                                            'code' => 200
                                        )
                                    );
                                    http_response_code(200);
                                } else {
                                    $response = array(
                                        'metadata' => array(
                                            'message' => 'Maaf belum ada antrian ditanggal ' . FormatTgl(("d-m-Y"),$decode['tanggalperiksa']),
                                             'code' => 201
                                        )
                                    );
                                    http_response_code(201);
                                }
                            }
                        }
                    }
                }else{
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "ambilantrean":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if (empty($decode['nomorkartu'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Kartu tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if (mb_strlen($decode['nomorkartu'], 'UTF-8') <> 13){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Kartu harus 13 digit',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if (!preg_match("/^[0-9]{13}$/",$decode['nomorkartu'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Nomor Kartu tidak sesuai',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }elseif (empty($decode['nik'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'NIK tidak boleh kosong ',
                                'code' => 201
                            )
                        ); 
                        http_response_code(201);
                    }elseif (strlen($decode['nik']) <> 16) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'NIK harus 16 digit ',
                                'code' => 201
                            )
                        ); 
                        http_response_code(201);
                    }else if (!preg_match("/^[0-9]{16}$/",$decode['nik'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format NIK tidak sesuai',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }elseif(empty($decode['nohp'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'No.HP tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['nohp'],"'")||strpos($decode['nohp'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format No.HP salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                   }elseif(empty($decode['norm'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'No.RM tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['norm'],"'")||strpos($decode['norm'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format No.RM salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                   }elseif(empty($decode['kodepoli'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Poli tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodepoli'],"'")||strpos($decode['kodepoli'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Poli tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                   }else if(empty($decode['kodedokter'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Dokter tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodedokter'],"'")||strpos($decode['kodedokter'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Dokter tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }elseif(empty($decode['tanggalperiksa'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$decode['tanggalperiksa'])){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Tanggal tidak sesuai, format yang benar adalah yyyy-mm-dd',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(date("Y-m-d")>$decode['tanggalperiksa']){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal Periksa tidak berlaku mundur',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(empty($decode['jampraktek'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jam Praktek tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['jampraktek'],"'")||strpos($decode['jampraktek'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jam Praktek tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(empty($decode['jeniskunjungan'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jenis Kunjungan tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['jeniskunjungan'],"'")||strpos($decode['jeniskunjungan'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jenis Kunjungan tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{1}$/",$decode['jeniskunjungan'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Jenis Kunjungan tidak sesuai',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!(($decode['jeniskunjungan']=="1")||($decode['jeniskunjungan']=="2")||($decode['jeniskunjungan']=="3")||($decode['jeniskunjungan']=="4"))){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Jenis Kunjungan tidak ditemukan',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(empty($decode['nomorreferensi'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Referensi tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['nomorreferensi'],"'")||strpos($decode['nomorreferensi'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Referensi tidak sesuai format',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(getOne2("select count(nomorreferensi) from referensi_mobilejkn_bpjs where nomorreferensi='$decode[nomorreferensi]'")>0){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Anda sudah terdaftar dalam antrian menggunakan nomor referensi yang sama',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else {
                        $kdpoli     = getOne2("SELECT kd_poli_rs FROM maping_poli_bpjs WHERE kd_poli_bpjs='$decode[kodepoli]'");
                        $kddokter   = getOne2("SELECT kd_dokter FROM maping_dokter_dpjpvclaim WHERE kd_dokter_bpjs='$decode[kodedokter]'");
                        $hari       = strtoupper(hariindo($decode['tanggalperiksa']));
                        if(empty($kdpoli)) { 
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Poli tidak ditemukan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else if(empty($kddokter)) { 
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Dokter tidak ditemukan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else{
                            $jammulai   = substr($decode['jampraktek'],0,5);
                            $jamselesai = substr($decode['jampraktek'],6,5);
                            $jadwal     = fetch_array(bukaquery2("select jadwal.kd_dokter,dokter.nm_dokter,jadwal.hari_kerja,jadwal.jam_mulai,jadwal.jam_selesai,jadwal.kd_poli,poliklinik.nm_poli,jadwal.kuota 
                                            from jadwal inner join poliklinik ON poliklinik.kd_poli=jadwal.kd_poli inner join dokter ON jadwal.kd_dokter=dokter.kd_dokter 
                                            where jadwal.hari_kerja='$hari' and jadwal.kd_dokter='$kddokter' and jadwal.kd_poli='$kdpoli' and jadwal.jam_mulai='$jammulai:00' and jadwal.jam_selesai='$jamselesai:00'"));
                            
                            if(empty($jadwal['kuota'])) {
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Pendaftaran ke Poli ini sedang tutup',
                                        'code' => 201
                                    )
                                );
                                http_response_code(201);
                            }else{
                                if(empty(cekpasien($decode['nik'],$decode['nomorkartu']))){ 
                                    $response = array(
                                        'metadata' => array(
                                            'message' =>  "Data pasien ini tidak ditemukan, silahkan melakukan registrasi pasien baru ke loket administrasi Kami",
                                            'code' => 201
                                        )
                                    ); 
                                    http_response_code(201);
                                }else{
                                    $sudahdaftar=getOne2("select count(reg_periksa.no_rawat) from reg_periksa inner join pasien on reg_periksa.no_rkm_medis=pasien.no_rkm_medis where reg_periksa.kd_poli='$kdpoli' and reg_periksa.kd_dokter='$kddokter' and reg_periksa.tgl_registrasi='$decode[tanggalperiksa]' and pasien.no_peserta='$decode[nomorkartu]' ");
                                    if($sudahdaftar>0){
                                        $response = array(
                                            'metadata' => array(
                                                'message' =>  "Nomor Antrean hanya dapat diambil 1 kali pada Tanggal, Dokter dan Poli yang sama",
                                                'code' => 201
                                            )
                                        ); 
                                    }else{
                                        $sekarang  = date("Y-m-d");
                                        $interval  = getOne2("select (TO_DAYS('$decode[tanggalperiksa]')-TO_DAYS('$sekarang'))");
                                        if($interval<=0){
                                            $response = array(
                                                'metadata' => array(
                                                    'message' => 'Pendaftaran ke Poli ini sudah tutup',
                                                    'code' => 201
                                                )
                                            );  
                                            http_response_code(201);
                                        }else{
                                            $sisakuota=getOne2("select count(no_rawat) from reg_periksa where kd_poli='$kdpoli' and kd_dokter='$kddokter' and tgl_registrasi='$decode[tanggalperiksa]' ");
                                            if ($sisakuota < $jadwal['kuota']) {
                                                $datapeserta     = cekpasien($decode['nik'],$decode['nomorkartu']);
                                                $noReg           = noRegPoli($kdpoli,$kddokter, $decode['tanggalperiksa']);
                                                $max             = getOne2("select ifnull(MAX(CONVERT(RIGHT(no_rawat,6),signed)),0)+1 from reg_periksa where tgl_registrasi='".$decode['tanggalperiksa']."'");
                                                $no_rawat        = str_replace("-","/",$decode['tanggalperiksa']."/").sprintf("%06s", $max);
                                                $statuspoli      = getOne2("select if((select count(no_rkm_medis) from reg_periksa where no_rkm_medis='$datapeserta[no_rkm_medis]' and kd_poli='$kdpoli')>0,'Lama','Baru' )");
                                                $dilayani        = $noReg*$waktutunggu;

                                                if($datapeserta["tahun"] > 0){
                                                    $umur       = $datapeserta["tahun"];
                                                    $sttsumur   = "Th";
                                                }else if($datapeserta["tahun"] == 0){
                                                    if($datapeserta["bulan"] > 0){
                                                        $umur       = $datapeserta["bulan"];
                                                        $sttsumur   = "Bl";
                                                    }else if($datapeserta["bulan"] == 0){
                                                        $umur       = $datapeserta["hari"];
                                                        $sttsumur   = "Hr";
                                                    }
                                                }
                                                $query = bukaquery2("insert into reg_periksa values('$noReg', '$no_rawat', '$decode[tanggalperiksa]',current_time(), '$kddokter', '$datapeserta[no_rkm_medis]', '$kdpoli', '$datapeserta[namakeluarga]', '$datapeserta[alamatpj], $datapeserta[kelurahanpj], $datapeserta[kecamatanpj], $datapeserta[kabupatenpj], $datapeserta[propinsipj]', '$datapeserta[keluarga]', '".getOne2("select registrasilama from poliklinik where kd_poli='$kdpoli'")."', 'Belum','Lama','Ralan', '".CARABAYAR."', '$umur','$sttsumur','Belum Bayar', '$statuspoli')");

                                                if ($query) {
                                                    $response = array(
                                                        'response' => array(
                                                            'nomorantrean' => $kdpoli."-".$noReg,
                                                            'angkaantrean' => $noReg,
                                                            'kodebooking'=> $no_rawat,
                                                            'pasienbaru'=>0,
                                                            'norm'=> $datapeserta['no_rkm_medis'],
                                                            'namapoli' => $jadwal['nm_poli'],
                                                            'namadokter' => $jadwal['nm_dokter'],
                                                            'estimasidilayani' => strtotime($jadwal['jam_mulai'].'+'.$dilayani.' minute')* 1000,
                                                            'sisakuotajkn'=>($jadwal['kuota']-$sisakuota-1)."",
                                                            'kuotajkn'=> $jadwal['kuota'],
                                                            'sisakuotanonjkn'=>($jadwal['kuota']-$sisakuota-1)."",
                                                            'kuotanonjkn'=> $jadwal['kuota'],
                                                            'keterangan'=> 'Peserta harap 30 menit lebih awal guna pencatatan administrasi.'
                                                        ),
                                                        'metadata' => array(
                                                            'message' => 'Ok',
                                                            'code' => 200
                                                        )
                                                    );
                                                    
                                                    $jeniskunjungan= "1 (Rujukan FKTP)";
                                                    if($decode['jeniskunjungan']=="1"){
                                                        $jeniskunjungan = "1 (Rujukan FKTP)";
                                                    }else if($decode['jeniskunjungan']=="2"){
                                                        $jeniskunjungan = "2 (Rujukan Internal)";
                                                    }else if($decode['jeniskunjungan']=="3"){
                                                        $jeniskunjungan = "3 (Kontrol)";
                                                    }else if($decode['jeniskunjungan']=="4"){
                                                        $jeniskunjungan = "4 (Rujukan Antar RS)";
                                                    }
                                                    bukaquery2("insert into referensi_mobilejkn_bpjs values('$no_rawat', '$decode[nomorkartu]', '$decode[nik]', '$decode[nohp]', '$decode[kodepoli]', '$decode[norm]', '$decode[tanggalperiksa]', '$decode[kodedokter]', '$decode[jampraktek]', '$jeniskunjungan', '$decode[nomorreferensi]','Belum','0000-00-00 00:00:00')");
                                                    http_response_code(200);
                                                } else {
                                                    $response = array(
                                                        'metadata' => array(
                                                            'message' => "Maaf terjadi kesalahan, hubungi Admnistrator..",
                                                            'code' => 401
                                                        )
                                                    );
                                                    http_response_code(401);
                                                }  
                                            }else{
                                                $response = array(
                                                    'metadata' => array(
                                                        'message' => 'Kuota penuuuh...!',
                                                        'code' => 201
                                                    )
                                                ); 
                                                http_response_code(201);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "checkinantrean":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    @$tanggal=date("Y-m-d", ($decode['waktu']/1000));
                    
                    if(empty($decode['kodebooking'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Booking tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodebooking'],"'")||strpos($decode['kodebooking'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Kode Booking salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }elseif(empty($decode['waktu'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Waktu tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$tanggal)){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Tanggal Checkin tidak sesuai, format yang benar adalah yyyy-mm-dd',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(date("Y-m-d")>$tanggal){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Waktu Checkin tidak berlaku mundur',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else{
                        $booking = fetch_array(bukaquery2("select no_rawat,tanggalperiksa,status,validasi from referensi_mobilejkn_bpjs where no_rawat='$decode[kodebooking]'"));
                        if(empty($booking['status'])) {
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Data Booking tidak ditemukan/Sudah Dibatalkan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else{
                            if($booking['status']=='Checkin'){
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Anda Sudah Checkin pada tanggal '.$booking['validasi'],
                                        'code' => 201
                                    )
                                );
                                http_response_code(201);
                            }else if($booking['status']=='Belum'){
                                $interval  = getOne2("select (TO_DAYS('$booking[tanggalperiksa]')-TO_DAYS('$tanggal'))");
                                if($interval<=0){
                                    $response = array(
                                        'metadata' => array(
                                            'message' => 'Chekin Anda sudah expired, maksimal 1 hari sebelum tanggal periksa. Silahkan konfirmasi ke loket pendaftaran',
                                            'code' => 201
                                        )
                                    );  
                                    http_response_code(201);
                                }else{
                                    $tanggalupdate=date("Y-m-d H:i:s", ($decode['waktu']/1000));
                                    $update=bukaquery2("update referensi_mobilejkn_bpjs set status='Checkin',validasi='$tanggalupdate' where no_rawat='$decode[kodebooking]'");
                                    if($update){
                                        $response = array(
                                            'metadata' => array(
                                                'message' => 'Ok',
                                                'code' => 200
                                            )
                                        );
                                        http_response_code(200);
                                    }else{
                                        $response = array(
                                            'metadata' => array(
                                                'message' => "Maaf terjadi kesalahan, hubungi Admnistrator..",
                                                'code' => 401
                                            )
                                        );
                                        http_response_code(401);
                                    }
                                }
                            }
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "batalantrean":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if(empty($decode['kodebooking'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Booking tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodebooking'],"'")||strpos($decode['kodebooking'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Kode Booking salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(empty($decode['keterangan'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Keterangan tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['keterangan'],"'")||strpos($decode['keterangan'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Keterangan salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else{
                        $booking = fetch_array(bukaquery2("select no_rawat,tanggalperiksa,status,validasi,nomorreferensi from referensi_mobilejkn_bpjs where no_rawat='$decode[kodebooking]'"));
                        if(empty($booking['status'])) {
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Data Booking tidak ditemukan/Sudah Dibatalkan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else{
                            if(date("Y-m-d")>$booking['tanggalperiksa']){
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Pembatalan Antrean tidak berlaku mundur',
                                        'code' => 201
                                    )
                                );  
                                http_response_code(201);
                            }else if($booking['status']=='Checkin'){
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Anda Sudah Checkin Pada Tanggal '.$booking['validasi'].', Pendaftaran Tidak Bisa Dibatalkan',
                                        'code' => 201
                                    )
                                );
                                http_response_code(201);
                            }else if($booking['status']=='Belum'){
                                $norm   = getOne2("select no_rkm_medis from reg_periksa where no_rawat='$decode[kodebooking]'");
                                $batal  = bukaquery2("delete from reg_periksa where no_rawat='$decode[kodebooking]'");
                                if($batal){
                                    $response = array(
                                        'metadata' => array(
                                            'message' => 'Ok',
                                            'code' => 200
                                        )
                                    );
                                    bukaquery2("insert into referensi_mobilejkn_bpjs_batal values('$norm','$booking[no_rawat]','$booking[nomorreferensi]',now(),'$decode[keterangan]')");
                                    http_response_code(200);
                                }else{
                                    $response = array(
                                        'metadata' => array(
                                            'message' => "Maaf Terjadi Kesalahan, Hubungi Admnistrator..",
                                            'code' => 401
                                        )
                                    );
                                    http_response_code(401);
                                }
                            }
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "sisaantrean":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if(empty($decode['kodebooking'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Kode Booking tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(strpos($decode['kodebooking'],"'")||strpos($decode['kodebooking'],"\\")){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Kode Booking salah',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else{
                        $booking = fetch_array(bukaquery2("select no_rawat,tanggalperiksa,status,validasi,nomorreferensi from referensi_mobilejkn_bpjs where no_rawat='$decode[kodebooking]'"));
                        if(empty($booking['status'])) {
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Data Booking tidak ditemukan/Sudah Dibatalkan',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }else{
                            if($booking['status']=='Belum'){
                                $response = array(
                                    'metadata' => array(
                                        'message' => 'Anda belum melakukan checkin, Silahkan checkin terlebih dahulu',
                                        'code' => 201
                                    )
                                );
                                http_response_code(201);
                            }else if($booking['status']=='Checkin'){
                                $data = fetch_array(bukaquery("SELECT reg_periksa.kd_poli,poliklinik.nm_poli,dokter.nm_dokter,
                                    reg_periksa.no_reg,COUNT(reg_periksa.no_rawat) as total_antrean,
                                    CONCAT(00,COUNT(reg_periksa.no_rawat)) as antrean_panggil,
                                    SUM(CASE WHEN reg_periksa.stts ='Belum' THEN 1 ELSE 0 END) as sisa_antrean,
                                    SUM(CASE WHEN reg_periksa.stts ='Sudah' THEN 1 ELSE 0 END) as sudah_selesai,
                                    ('Datanglah Minimal 30 Menit, jika no antrian anda terlewat, silakan konfirmasi ke bagian Pendaftaran atau Perawat Poli, Terima Kasih ..') as keterangan
                                    FROM reg_periksa INNER JOIN poliklinik ON poliklinik.kd_poli=reg_periksa.kd_poli
                                    INNER JOIN dokter ON dokter.kd_dokter=reg_periksa.kd_dokter
                                    WHERE reg_periksa.no_rawat='$decode[kodebooking]'"));

                                if ($data['nm_poli'] != '') {
                                    $response = array(
                                        'response' => array(
                                            'nomorantrean' => $data['kd_poli']."-".$data['no_reg'],
                                            'namapoli' => $data['nm_poli'],
                                            'namadokter' => $data['nm_dokter'],
                                            'sisaantrean' => $data['sisa_antrean'],
                                            'antreanpanggil' => $data['kd_poli']."-".$data['no_reg'],
                                            'waktutunggu' => (($data['sisa_antrean']*$waktutunggu)*1000),
                                            'keterangan' => $data['keterangan']
                                        ),
                                        'metadata' => array(
                                            'message' => 'Ok',
                                            'code' => 200
                                        )
                                    );
                                    http_response_code(200);
                                } else {
                                    $response = array(
                                        'metadata' => array(
                                            'message' => 'Antrean Tidak Ditemukan !',
                                            'code' => 201
                                        )
                                    );
                                    http_response_code(201);
                                } 
                            }
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "jadwaloperasirs":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if(empty($decode['tanggalawal'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal Awal tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$decode['tanggalawal'])){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Tanggal Awal tidak sesuai, format yang benar adalah yyyy-mm-dd',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(date("Y-m-d")>$decode['tanggalawal']){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal Awal tidak berlaku mundur',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(empty($decode['tanggalakhir'])) { 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal Akhir tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/",$decode['tanggalakhir'])){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Tanggal Akhir tidak sesuai, format yang benar adalah yyyy-mm-dd',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if(date("Y-m-d")>$decode['tanggalakhir']){
                        $response = array(
                            'metadata' => array(
                                'message' => 'Tanggal Akhir tidak berlaku mundur',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else if ($decode['tanggalawal'] > $decode['tanggalakhir']) {
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format tanggal awal harus lebih kecil dari tanggal akhir',
                                'code' => 201
                            )
                        );  
                        http_response_code(201);
                    }else{
                        $queryoperasirs = bukaquery2("SELECT booking_operasi.no_rawat,booking_operasi.tanggal,paket_operasi.nm_perawatan,maping_poli_bpjs.kd_poli_bpjs,maping_poli_bpjs.nm_poli_bpjs,booking_operasi.status,pasien.no_peserta 
                                            FROM booking_operasi INNER JOIN reg_periksa ON booking_operasi.no_rawat = reg_periksa.no_rawat INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis INNER JOIN paket_operasi ON booking_operasi.kode_paket = paket_operasi.kode_paket 
                                            INNER JOIN maping_poli_bpjs ON maping_poli_bpjs.kd_poli_rs=reg_periksa.kd_poli WHERE booking_operasi.tanggal BETWEEN '$decode[tanggalawal]' AND '$decode[tanggalakhir]' ORDER BY booking_operasi.tanggal,booking_operasi.jam_mulai");
                        if(num_rows($queryoperasirs)>0) {
                            while($rsqueryoperasirs = mysqli_fetch_array($queryoperasirs)) {
                                $status=0;
                                if($rsqueryoperasirs['status'] == 'Menunggu') {
                                    $status = 0;
                                } else {
                                    $status = 1;
                                }
                                $data_array[] = array(
                                    'kodebooking' => $rsqueryoperasirs['no_rawat'],
                                    'tanggaloperasi' => $rsqueryoperasirs['tanggal'],
                                    'jenistindakan' => $rsqueryoperasirs['nm_perawatan'],
                                    'kodepoli' => $rsqueryoperasirs['kd_poli_bpjs'],
                                    'namapoli' => $rsqueryoperasirs['nm_poli_bpjs'],
                                    'terlaksana' => $status,
                                    'nopeserta' => $rsqueryoperasirs['no_peserta'],
                                    'lastupdate' => strtotime(date('Y-m-d H:i:s')) * 1000
                                );
                            }
                            $response = array(
                                'response' => array(
                                    'list' => (
                                        $data_array
                                    )
                                ),
                                'metadata' => array(
                                    'message' => 'Ok',
                                    'code' => 200
                                )
                            );
                            http_response_code(200);
                        }else{
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Maaf tidak ada Jadwal Operasi pada tanggal tersebut',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
            case "jadwaloperasipasien":
                $header = apache_request_headers();
                $konten = trim(file_get_contents("php://input"));
                $decode = json_decode($konten, true);
                if((!empty($header['x-token'])) && (USERNAME==$header['x-username']) && (PASSWORD==$header['x-password']) && (cektoken($header['x-token'])=='true')){
                    if (empty($decode['nopeserta'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Peserta tidak boleh kosong',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if (mb_strlen($decode['nopeserta'], 'UTF-8') <> 13){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Nomor Peserta harus 13 digit',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else if (!preg_match("/^[0-9]{13}$/",$decode['nopeserta'])){ 
                        $response = array(
                            'metadata' => array(
                                'message' => 'Format Nomor Peserta tidak sesuai',
                                'code' => 201
                            )
                        );
                        http_response_code(201);
                    }else{
                        $queryoperasipasien = bukaquery2("SELECT booking_operasi.no_rawat,booking_operasi.tanggal,paket_operasi.nm_perawatan,maping_poli_bpjs.kd_poli_bpjs,maping_poli_bpjs.nm_poli_bpjs,booking_operasi.status,pasien.no_peserta 
                                            FROM booking_operasi INNER JOIN reg_periksa ON booking_operasi.no_rawat = reg_periksa.no_rawat INNER JOIN pasien ON pasien.no_rkm_medis = reg_periksa.no_rkm_medis INNER JOIN paket_operasi ON booking_operasi.kode_paket = paket_operasi.kode_paket 
                                            INNER JOIN maping_poli_bpjs ON maping_poli_bpjs.kd_poli_rs=reg_periksa.kd_poli WHERE pasien.no_peserta='$decode[nopeserta]' ORDER BY booking_operasi.tanggal,booking_operasi.jam_mulai");
                        if(num_rows($queryoperasipasien)>0) {
                            while($rsqueryoperasipasien = mysqli_fetch_array($queryoperasipasien)) {
                                $status=0;
                                if($rsqueryoperasipasien['status'] == 'Menunggu') {
                                    $status = 0;
                                } else {
                                    $status = 1;
                                }
                                $data_array[] = array(
                                    'kodebooking' => $rsqueryoperasipasien['no_rawat'],
                                    'tanggaloperasi' => $rsqueryoperasipasien['tanggal'],
                                    'jenistindakan' => $rsqueryoperasipasien['nm_perawatan'],
                                    'kodepoli' => $rsqueryoperasipasien['kd_poli_bpjs'],
                                    'namapoli' => $rsqueryoperasipasien['nm_poli_bpjs'],
                                    'terlaksana' => $status
                                );
                            }
                            $response = array(
                                'response' => array(
                                    'list' => (
                                        $data_array
                                    )
                                ),
                                'metadata' => array(
                                    'message' => 'Ok',
                                    'code' => 200
                                )
                            );
                            http_response_code(200);
                        }else{
                            $response = array(
                                'metadata' => array(
                                    'message' => 'Maaf anda tidak memiliki jadwal operasi',
                                    'code' => 201
                                )
                            );
                            http_response_code(201);
                        }
                    }
                }else {
                    $response = array(
                        'metadata' => array(
                            'message' => 'Nama User / Password / Token ada yang salah ..!!',
                            'code' => 201
                        )
                    );
                    http_response_code(201);
                }
                break;
        }
    }
    
    if (!empty($response)) {
        echo json_encode($response);
    } else {
        $instansi=fetch_assoc(bukaquery2("select nama_instansi from setting"));
        echo "Selamat Datang di Web Service Antrean BPJS Mobile JKN FKTL ".$instansi['nama_instansi']." ".date('Y');
        echo "\n\n";
        echo "Cara Menggunakan Web Service Antrean BPJS Mobile JKN FKTL : \n";
        echo "1. Mengambil Token, methode GET \n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/auth \n";
        echo "   Header gunakan x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '         "token": "xxxxxxxxxxxxxxxxx'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '         "message": "Ok",'."\n";
        echo '         "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "2. Menampilkan status atrean poli, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/statusantrean \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
	echo '      "kodepoli":"XXX",'."\n";
	echo '      "kodedokter":"XXXXX",'."\n";
	echo '      "tanggalperiksa":"XXXX-XX-XX",'."\n";
	echo '      "jampraktek":"XX:XX-XX:XX"'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '          "namapoli": "XXXXXXXXXXXXXX",'."\n";
        echo '          "namadokter": "XXXXXXXXXXXXXX",'."\n";
        echo '          "totalantrean": "X",'."\n";
        echo '          "sisaantrean": "X",'."\n";
        echo '          "antreanpanggil": "X-XX",'."\n";
        echo '          "sisakuotajkn": "XX",'."\n";
        echo '          "kuotajkn": "XX",'."\n";
        echo '          "sisakuotanonjkn": "XX",'."\n";
        echo '          "kuotanonjkn": "XX",'."\n";
        echo '          "keterangan": "XXXXXXXXXXXXXX"'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "3. Mengambil atrean poli, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/ambilantrean \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "nomorkartu": "XXXXXXXXXXXXXX",'."\n";
        echo '      "nik": "XXXXXXXXXXXXXXXXX",'."\n";
        echo '      "nohp": "XXXXXXXX",'."\n";
        echo '      "kodepoli": "XXX",'."\n";
        echo '      "norm": "XXXXX",'."\n";
        echo '      "tanggalperiksa": "XXXX-XX-XX",'."\n";
        echo '      "kodedokter": "XXXXX",'."\n";
        echo '      "jampraktek": "XX:XX-XX:XX",'."\n";
        echo '      "jeniskunjungan": x,'."\n";
        echo '      "nomorreferensi": "XXXXXXXXXXXX"'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '          "nomorantrean": "X-XXX",'."\n";
        echo '          "angkaantrean": "XXX",'."\n";
        echo '          "kodebooking": "XXXX/XX/XX/XXXXX",'."\n";
        echo '          "pasienbaru": X,'."\n";
        echo '          "norm": "XXXXXX",'."\n";
        echo '          "namapoli": "XXXXXXXXXXXXXXX",'."\n";
        echo '          "namadokter": "XXXXXXXXXXXXXXX",'."\n";
        echo '          "estimasidilayani": XXXXXXX,'."\n";
        echo '          "sisakuotajkn": "XX",'."\n";
        echo '          "kuotajkn": "XX",'."\n";
        echo '          "sisakuotanonjkn": "XXX",'."\n";
        echo '          "kuotanonjkn": "XXX",'."\n";
        echo '          "keterangan": "XXXXXXXXXXXXXXXX"'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "4. Melakukan checkin poli, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/checkinantrean \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "kodebooking": "XXXXXXXXXXXXXX",'."\n";
        echo '      "waktu": XXXXXXXXXXX(timestamp milliseconds)'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "5. Membatalkan antrean poli dan hanya bisa dilakukan sebelum pasien checkin, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/batalantrean \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "kodebooking": "XXXXXXXXXXXXXX",'."\n";
        echo '      "keterangan": XXXXXXXXXXXXXXXXXXXXXXX'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "6. Melihat sisa antrean poli dan hanya bisa dilakukan setelah pasien checkin, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/sisaantrean \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "kodebooking": "XXXXXXXXXXXXXX"'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '          "nomorantrean": "XXXX",'."\n";
        echo '          "namapoli": "XXXXXXXXXXXX",'."\n";
        echo '          "namadokter": "XXXXXXXXXXX",'."\n";
        echo '          "sisaantrean": XX,'."\n";
        echo '          "antreanpanggil": "XXXX",'."\n";
        echo '          "waktutunggu": XXXX,'."\n";
        echo '          "keterangan": "XXXXX"'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "7. Melihat Jadwal Operasi RS, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/jadwaloperasirs \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "tanggalawal": "XXXX-XX-XX"'."\n";
        echo '      "tanggalakhir": "XXXX-XX-XX"'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '          "list": ['."\n";
        echo '              {'."\n";
        echo '                  "kodebooking": "XXXXXXXXX",'."\n";
        echo '                  "tanggaloperasi": "XXXX-XX-XX",'."\n";
        echo '                  "jenistindakan": "XXXXXXXXXXXXXXXXX",'."\n";
        echo '                  "kodepoli": "XXX",'."\n";
        echo '                  "namapoli": "XXXXXXXXXXXXX",'."\n";
        echo '                  "terlaksana": X,'."\n";
        echo '                  "nopeserta": "XXXXXXXXXX",'."\n";
        echo '                  "lastupdate": XXXXXXXX'."\n";
        echo '              },'."\n";
        echo '           ]'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
        echo "8. Melihat Jadwal Operasi Pasien, methode POST\n";
        echo "   gunakan URL http://ipserverws:port/webapps/api-bpjsfktl/jadwaloperasipasien \n";
        echo "   Header gunakan x-token:token yang diambil sebelumnya, x-username:user yang diberikan RS, x-password:pass yang diberikan RS\n";
        echo "   Body berisi : \n";
        echo '   {'."\n";
        echo '      "nopeserta": "XXXXXXXXXX"'."\n";
        echo '   }'."\n\n";
        echo "   Hasilnya : \n";
        echo '   {'."\n";
        echo '      "response": {'."\n";
        echo '          "list": ['."\n";
        echo '              {'."\n";
        echo '                  "kodebooking": "XXXXXXXXX",'."\n";
        echo '                  "tanggaloperasi": "XXXX-XX-XX",'."\n";
        echo '                  "jenistindakan": "XXXXXXXXXXXXXXXXX",'."\n";
        echo '                  "kodepoli": "XXX",'."\n";
        echo '                  "namapoli": "XXXXXXXXXXXXX",'."\n";
        echo '                  "terlaksana": X'."\n";
        echo '              },'."\n";
        echo '           ]'."\n";
        echo '      },'."\n";
        echo '      "metadata": {'."\n";
        echo '          "message": "Ok",'."\n";
        echo '          "code": 200'."\n";
        echo '      }'."\n";
        echo '   }'."\n\n";
    }
?>