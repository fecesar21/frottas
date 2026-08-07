<?php

namespace Database\Seeders;

use App\Models\Motorista;
use Illuminate\Database\Seeder;

class MotoristaSeeder extends Seeder
{
    public function run(): void
    {
        $motoristas = [
            ['nome' => 'ALEX SANDRO PEREIRA DA SILVA', 'cpf' => '089.249.650-93', 'cnh_numero' => '3561145161', 'cnh_categoria' => 'D', 'cnh_validade' => '2034-06-11', 'turno_padrao' => 'noite'],
            ['nome' => 'AMADEU FERREIRA LIMA', 'cpf' => '962.590.390-93', 'cnh_numero' => '3242167649', 'cnh_categoria' => 'D', 'cnh_validade' => '2032-01-15', 'turno_padrao' => 'dia'],
            ['nome' => 'DEMILSON RIBEIRO LEITE', 'cpf' => '630.552.030-54', 'cnh_numero' => '94144178188', 'cnh_categoria' => 'D', 'cnh_validade' => '2033-05-04', 'turno_padrao' => 'dia'],
            ['nome' => 'EDIMAR ALVES DA SILVA', 'cpf' => '419.881.240-34', 'cnh_numero' => '4339498654', 'cnh_categoria' => 'D', 'cnh_validade' => '2027-11-30', 'turno_padrao' => 'noite'],
            ['nome' => 'FREDSON PEREIRA LOPES', 'cpf' => '769.891.710-06', 'cnh_numero' => '2014034487', 'cnh_categoria' => 'D', 'cnh_validade' => '2030-03-28', 'turno_padrao' => 'dia'],
            ['nome' => 'GENIVAL RIBEIRO DOS SANTOS', 'cpf' => '249.251.720-93', 'cnh_numero' => '733665805', 'cnh_categoria' => 'D', 'cnh_validade' => '2031-04-26', 'turno_padrao' => 'dia'],
            ['nome' => 'HORLANDO DIAS DA LUZ', 'cpf' => '809.772.870-25', 'cnh_numero' => '3962494412', 'cnh_categoria' => 'D', 'cnh_validade' => '2033-06-23', 'turno_padrao' => 'noite'],
            ['nome' => 'JESUS ELIAS DA SILVA', 'cpf' => '726.136.730-36', 'cnh_numero' => '1478693764', 'cnh_categoria' => 'D', 'cnh_validade' => '2028-05-23', 'turno_padrao' => 'dia'],
            ['nome' => 'JOSÉ DA ROCHA', 'cpf' => '564.280.840-07', 'cnh_numero' => '947641391', 'cnh_categoria' => 'D', 'cnh_validade' => '2033-05-22', 'turno_padrao' => 'noite'],
            ['nome' => 'JOSÉ DIAS CARVALHO', 'cpf' => '483.771.920-12', 'cnh_numero' => '1707899411', 'cnh_categoria' => 'E', 'cnh_validade' => '2027-01-31', 'turno_padrao' => 'noite'],
            ['nome' => 'LUCAS BITTENCOURT DA SILVA', 'cpf' => '107.847.120-77', 'cnh_numero' => '5421542777', 'cnh_categoria' => 'D', 'cnh_validade' => '2032-08-09', 'turno_padrao' => 'noite'],
            ['nome' => 'MANOEL FILHO DE ASSIS', 'cpf' => '052.903.250-34', 'cnh_numero' => '3634519798', 'cnh_categoria' => 'D', 'cnh_validade' => '2033-11-04', 'turno_padrao' => 'noite'],
            ['nome' => 'NILSON PIRES SANTANA', 'cpf' => '465.183.450-71', 'cnh_numero' => '2889960101', 'cnh_categoria' => 'D', 'cnh_validade' => '2028-02-08', 'turno_padrao' => 'dia'],
            ['nome' => 'THIAGO ALEXANDRE DE OLIVEIRA FRANCO', 'cpf' => '685.339.480-18', 'cnh_numero' => '2404981370', 'cnh_categoria' => 'D', 'cnh_validade' => '2032-06-21', 'turno_padrao' => 'dia'],
        ];

        foreach ($motoristas as $dados) {
            Motorista::firstOrCreate(['cpf' => $dados['cpf']], array_merge($dados, [
                'status' => 'ativo',
                'telefone' => '(11) 9'.rand(1000, 9999).'-'.rand(1000, 9999),
            ]));
        }
    }
}
