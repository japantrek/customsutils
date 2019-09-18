<?php
namespace nvbooster\CustomsUtils;

/**
 * Функции для расчетов таможенных платежей
 *
 * @author nvb <nvb@aproxima.ru>
 */
class CustomsUtils
{
    const AGE_NEW = 'new';
    const AGE_USED_S = 'used_s';
    const AGE_USED_E = 'used_e';
    const AGE_OLD = 'old';

    const TYPE_PETROL = 'petrol';
    const TYPE_DIESEL = 'diesel';
    const TYPE_ELECTRO = 'electro';


    /**
     * Базовая ставка утилизационного сбора
     *
     * @param string $lightVehicle
     *
     * @return integer
     */
    public static function getRecycleTaxBase($lightVehicle = true)
    {
        return $lightVehicle ? 20000 : 150000;
    }

    /**
     * Коэфициент утилизацонного сбора
     *
     * @param string  $ageClass
     * @param boolean $privateUse
     * @param boolean $lightVehicle
     * @param integer $volume
     * @param integer $mass
     * @param boolean $electro
     *
     * @return double
     */
    public static function getRecycleRate($ageClass, $privateUse = true, $lightVehicle = true, $volume = 0, $mass = 0, $electro = false)
    {
        $rates = array(
            // I. M1, (G)
            '1.' => array(1.63, 6.1),
            '2.a' => array(1.65, 6.15),
            '2.b' => array(4.2, 15.69),
            '2.c' => array(6.3, 24.01),
            '2.d' => array(5.73, 28.5),
            '2.e' => array(9.08, 35.01),
            '3.' => array(0.17, 0.26),

            // II. N1, N2, N3 (G)
            '4.' => array(0.95, 1.01),
            '5.' => array(2, 2.88),
            '6.' => array(1.9, 3.04),
            '7.' => array(2.09, 5.24),
            '8.' => array(2.54, 7.95),
            '9.' => array(2.79, 11.57),
            '10.' => array(5.5, 13.57)
        );

        $group = '';

        if ($lightVehicle) {
            if ($privateUse) {
                $group = '3.';
            } elseif ($electro) {
                $group = '1.';
            } else {
                $group = '2.';
                if ($volume <= 1000) {
                    $group .= 'a';
                } elseif ($volume <= 2000) {
                    $group .= 'b';
                } elseif ($volume <= 3000) {
                    $group .= 'c';
                } elseif ($volume <= 3500) {
                    $group .= 'd';
                } else {
                    $group .= 'e';
                }
            }
        } else {
            if ($mass <= 2.5) {
                $group = '4.';
            } elseif ($mass <= 3.5) {
                $group = '5.';
            } elseif ($mass <= 5) {
                $group = '6.';
            } elseif ($mass <= 8) {
                $group = '7.';
            } elseif ($mass <= 12) {
                $group = '8.';
            } elseif ($mass <= 20) {
                $group = '9.';
            } else {
                $group = '10.';
            }
        }

        return $rates[$group][($ageClass == self::AGE_NEW) ? 0 : 1];
    }

    /**
     * Стоймость таможенного оформления
     *
     * @param integer $declaredCost
     *
     * @return integer
     */
    public static function getCustomsFee($declaredCost)
    {
        if ($declaredCost <= 200000) {
            $fee = 500;
        } elseif ($declaredCost <= 450000) {
            $fee = 1000;
        } elseif ($declaredCost <= 1200000) {
            $fee = 2000;
        } elseif ($declaredCost <= 2500000) {
            $fee = 5500;
        } elseif ($declaredCost <= 5000000) {
            $fee = 7500;
        } elseif ($declaredCost <= 10000000) {
            $fee = 20000;
        } else {
            $fee = 30000;
        }

        return $fee;
    }

    /**
     * Применима ли единая таможенная ставка
     *
     * @param boolean $privateUse
     * @param boolean $lightVehicle
     * @param string  $ageClass
     * @param boolean $electro
     *
     * @return boolean
     */
    public static function isETSApplied($privateUse, $lightVehicle, $ageClass, $electro = false)
    {
        return $privateUse && $lightVehicle && (!$electro || self::AGE_NEW == $ageClass);
    }

    /**
     * Ставки пошлины (% от стоймости и ставка за 1куб.см)
     *
     * @param integer $declaredCost
     * @param string  $ageClass
     * @param integer $volume
     * @param string  $fueltype
     * @param integer $mass
     * @param boolean $privateUse
     * @param boolean $lightVehicle
     *
     * @return array
     */
    public static function getTaxRates($declaredCost, $ageClass, $volume, $fueltype, $mass, $privateUse = true, $lightVehicle = true)
    {
        if (self::isETSApplied($privateUse, $lightVehicle, $ageClass, $fueltype == self::TYPE_ELECTRO)) {
            return self::getETSRates($declaredCost, $ageClass, $volume);
        } else {
            $rates = self::getETTRates();
            if (($code = self::getTNVED($lightVehicle, $volume, $ageClass, $fueltype, $mass)) && key_exists($code, $rates)) {
                return $rates[$code];
            } else {
                return false;
            }

        }
    }

    /**
     * @param integer $declaredCost
     * @param string  $ageClass
     * @param integer $volume
     *
     * @return double
     */
    public static function getETSRates($declaredCost, $ageClass, $volume)
    {
        if ($ageClass == self::AGE_NEW) {
            $result = array('dcb' => 48, 'vb' => 0);

            if ($declaredCost <= 8500) {
                $result['dcb'] = 54;
                $result['vb'] = 2.5;
            } elseif ($declaredCost <= 16700) {
                $result['vb'] = 3.5;
            } elseif ($declaredCost <= 42300) {
                $result['vb'] = 5.5;
            } elseif ($declaredCost <= 84500) {
                $result['vb'] = 7.5;
            } elseif ($declaredCost <= 169000) {
                $result['vb'] = 15;
            } else {
                $result['vb'] = 20;
            }
        } else {
            $base = $ageClass == self::AGE_USED_S;
            $result = array('dcb' => 0, 'vb' => 0);

            if ($volume <= 1000) {
                $result['vb'] = ($base ? 1.5 : 3);
            } elseif ($volume <= 1500) {
                $result['vb'] = ($base ? 1.7 : 3.2);
            } elseif ($volume <= 1800) {
                $result['vb'] = ($base ? 2.5 : 3.5);
            } elseif ($volume <= 2300) {
                $result['vb'] = ($base ? 2.7 : 4.8);
            } elseif ($volume <= 3000) {
                $result['vb'] = ($base ? 3 : 5);
            } else {
                $result['vb'] = ($base ? 3.6 : 5.7);
            }
        }

        return $result;
    }

    /**
     * Ставки пошлины по кодам ТН ВЭД
     *
     * @return array
     */
    public static function getETTRates()
    {
        $result = array(
            '8703 21 109 9' => array('dcb' => 15, 'vb' => 0),
            '8703 21 909 3' => array('dcb' => 0, 'vb' => 1.4),
            '8703 21 909 4' => array('dcb' => 20, 'vb' => .36),
            '8703 21 909 8' => array('dcb' => 20, 'vb' => .36),

            '8703 22 109 9' => array('dcb' => 15, 'vb' => 0),
            '8703 22 909 3' => array('dcb' => 0, 'vb' => 1.5),
            '8703 22 909 4' => array('dcb' => 20, 'vb' => .4),
            '8703 22 909 8' => array('dcb' => 20, 'vb' => .4),

            '8703 23 194 0' => array('dcb' => 15, 'vb' => 0),
            '8703 23 198 1' => array('dcb' => 15, 'vb' => 0),
            '8703 23 198 2' => array('dcb' => 15, 'vb' => 0),
            '8703 23 198 3' => array('dcb' => 15, 'vb' => 0),
            '8703 23 198 8' => array('dcb' => 12.5, 'vb' => 0),
            '8703 23 904 1' => array('dcb' => 0, 'vb' => 1.6),
            '8703 23 904 2' => array('dcb' => 20, 'vb' => .36),
            '8703 23 904 9' => array('dcb' => 20, 'vb' => .36),
            '8703 23 908 1' => array('dcb' => 0, 'vb' => 2.2),
            '8703 23 908 2' => array('dcb' => 20, 'vb' => .44),
            '8703 23 908 3' => array('dcb' => 20, 'vb' => .44),
            '8703 23 908 7' => array('dcb' => 0, 'vb' => 2.2),
            '8703 23 908 8' => array('dcb' => 20, 'vb' => .44),
            '8703 23 908 9' => array('dcb' => 20, 'vb' => .44),

            '8703 24 109 8' => array('dcb' => 12.5, 'vb' => 0),
            '8703 24 909 3' => array('dcb' => 0, 'vb' => 3.2),
            '8703 24 909 4' => array('dcb' => 20, 'vb' => .8),
            '8703 24 909 8' => array('dcb' => 20, 'vb' => .8),

            '8703 31 109 0' => array('dcb' => 15, 'vb' => 0),
            '8703 31 909 3' => array('dcb' => 0, 'vb' => 1.5),
            '8703 31 909 4' => array('dcb' => 20, 'vb' => .32),
            '8703 31 909 8' => array('dcb' => 20, 'vb' => .32),

            '8703 32 199 0' => array('dcb' => 15, 'vb' => 0),
            '8703 32 909 3' => array('dcb' => 0, 'vb' => 2.2),
            '8703 32 909 4' => array('dcb' => 20, 'vb' => .4),
            '8703 32 909 8' => array('dcb' => 20, 'vb' => .4),

            '8703 33 199 0' => array('dcb' => 15, 'vb' => 0),
            '8703 33 909 3' => array('dcb' => 0, 'vb' => 3.2),
            '8703 33 909 4' => array('dcb' => 20, 'vb' => .8),
            '8703 33 909 8' => array('dcb' => 20, 'vb' => .8),

            '8703 80 000 2' => array('dcb' => 15, 'vb' => 0),

            //------------------
            '8704 21 310 0' => array('dcb' => 10, 'vb' => 0),
            '8704 21 390 3' => array('dcb' => 0, 'vb' => 1),
            '8704 21 390 4' => array('dcb' => 10, 'vb' => 0),
            '8704 21 390 8' => array('dcb' => 10, 'vb' => 0),

            '8704 21 910 0' => array('dcb' => 10, 'vb' => 0),
            '8704 21 990 3' => array('dcb' => 0, 'vb' => 1),
            '8704 21 990 4' => array('dcb' => 10, 'vb' => .13),
            '8704 21 990 8' => array('dcb' => 10, 'vb' => 0),

            '8704 22 910 8' => array('dcb' => 15, 'vb' => 0),
            '8704 22 990 4' => array('dcb' => 0, 'vb' => 1),
            '8704 22 990 5' => array('dcb' => 10, 'vb' => .18),
            '8704 22 990 7' => array('dcb' => 10, 'vb' => 0),

            '8704 23 910 8' => array('dcb' => 5, 'vb' => 0),
            '8704 23 990 4' => array('dcb' => 0, 'vb' => 1),
            '8704 23 990 5' => array('dcb' => 10, 'vb' => 0),
            '8704 23 990 7' => array('dcb' => 10, 'vb' => 0),

            '8704 31 310 0' => array('dcb' => 12.5, 'vb' => 0),
            '8704 31 390 3' => array('dcb' => 0, 'vb' => 1),
            '8704 31 390 4' => array('dcb' => 15, 'vb' => 0),
            '8704 31 390 8' => array('dcb' => 15, 'vb' => 0),

            '8704 31 910 0' => array('dcb' => 15, 'vb' => 0),
            '8704 31 990 3' => array('dcb' => 0, 'vb' => 1),
            '8704 31 990 4' => array('dcb' => 15, 'vb' => 0),
            '8704 31 990 8' => array('dcb' => 15, 'vb' => 0),

            '8704 32 910 9' => array('dcb' => 15, 'vb' => 0),
            '8704 32 990 4' => array('dcb' => 0, 'vb' => 1),
            '8704 32 990 5' => array('dcb' => 15, 'vb' => 0),
            '8704 32 990 7' => array('dcb' => 15, 'vb' => 0)
        );

        return $result;
    }

    /**
     * @param boolean $lightVehicle
     * @param integer $volume
     * @param string  $ageClass
     * @param string  $fueltype
     * @param integer $mass
     *
     * @return string
     */
    public static function getTNVED($lightVehicle, $volume, $ageClass, $fueltype, $mass)
    {
        $code = '87';

        if ($lightVehicle) {
            $code .= '03';
            if (self::TYPE_PETROL == $fueltype) {
                $code .= ' 2';
                if ($volume <= 1000) {
                    $code .= '1';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 109 9';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                } elseif ($volume <= 1500) {
                    $code .= '2';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 109 9';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                } elseif ($volume <= 3000) {
                    $code .= '3';
                    if (self::AGE_NEW == $ageClass) {
                        if ($volume <= 1800) {
                            $code .= ' 194 0';
                        } elseif ($volume <= 2300) {
                            $code .= ' 198 1';
                        } elseif ($volume <= 2800) {
                            $code .= ' 198 2';
                        } elseif ($volume <= 3000) {
                            $code .= ' 198 3';
                        } else {
                            $code .= ' 198 8';
                        }
                    } else {
                        if ($volume <= 1800) {
                            $code .= ' 904';
                            if (self::AGE_OLD == $ageClass) {
                                $code .= ' 1';
                            } elseif (self::AGE_USED_E == $ageClass) {
                                $code .= ' 2';
                            } else {
                                $code .= ' 9';
                            }
                        } else {
                            $code .= ' 908';
                            if ($volume <= 2300) {
                                if (self::AGE_OLD == $ageClass) {
                                    $code .= ' 1';
                                } elseif (self::AGE_USED_E == $ageClass) {
                                    $code .= ' 2';
                                } else {
                                    $code .= ' 3';
                                }
                            } else {
                                if (self::AGE_OLD == $ageClass) {
                                    $code .= ' 7';
                                } elseif (self::AGE_USED_E == $ageClass) {
                                    $code .= ' 8';
                                } else {
                                    $code .= ' 9';
                                }
                            }
                        }
                    }
                } else {
                    $code .= '4';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 109 8';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                }
            } elseif (self::TYPE_DIESEL == $fueltype) {
                $code .= ' 3';
                if ($volume <= 1500) {
                    $code .= '1';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 109 0';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                } elseif ($volume <= 2500) {
                    $code .= '2';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 199 0';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                } else {
                    $code .= '3';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 199 0';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 909 3';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 909 4';
                    } else {
                        $code .= ' 909 8';
                    }
                }
            } elseif (self::TYPE_ELECTRO == $fueltype) {
                $code .= ' 80 000 2';
            } else {
                return false;
            }
        } else {
            $code .= '04';
            if (self::TYPE_DIESEL == $fueltype) {
                $code .= ' 2';
                if ($mass <= 5) {
                    $code .= '1';
                    if ($volume > 2500) {
                        $code .= ' 3';
                        if (self::AGE_NEW == $ageClass) {
                            $code .= '10 0';
                        } elseif (self::AGE_OLD == $ageClass) {
                            $code .= '90 3';
                        } elseif (self::AGE_USED_E == $ageClass) {
                            $code .= '90 4';
                        } else {
                            $code .= '90 8';
                        }
                    } else {
                        $code .= ' 9';
                        if (self::AGE_NEW == $ageClass) {
                            $code .= '10 0';
                        } elseif (self::AGE_OLD == $ageClass) {
                            $code .= '90 3';
                        } elseif (self::AGE_USED_E == $ageClass) {
                            $code .= '90 4';
                        } else {
                            $code .= '90 8';
                        }
                    }
                } elseif ($mass <= 20) {
                    $code .= ' 22';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 910 8';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 990 4';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 990 5';
                    } else {
                        $code .= ' 990 7';
                    }
                } else {
                    $code .= ' 23';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 910 8';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 990 4';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 990 5';
                    } else {
                        $code .= ' 990 7';
                    }
                }
            } elseif (self::TYPE_PETROL == $fueltype) {
                $code .= ' 3';
                if ($mass <= 5) {
                    $code .= '1';
                    if ($volume > 2800) {
                        $code .= ' 3';
                        if (self::AGE_NEW == $ageClass) {
                            $code .= '10 0';
                        } elseif (self::AGE_OLD == $ageClass) {
                            $code .= '90 3';
                        } elseif (self::AGE_USED_E == $ageClass) {
                            $code .= '90 4';
                        } else {
                            $code .= '90 8';
                        }
                    } else {
                        $code .= ' 9';
                        if (self::AGE_NEW == $ageClass) {
                            $code .= '10 0';
                        } elseif (self::AGE_OLD == $ageClass) {
                            $code .= '90 3';
                        } elseif (self::AGE_USED_E == $ageClass) {
                            $code .= '90 4';
                        } else {
                            $code .= '90 8';
                        }
                    }
                } else {
                    $code .= '2';
                    if (self::AGE_NEW == $ageClass) {
                        $code .= ' 910 9';
                    } elseif (self::AGE_OLD == $ageClass) {
                        $code .= ' 990 4';
                    } elseif (self::AGE_USED_E == $ageClass) {
                        $code .= ' 990 5';
                    } else {
                        $code .= ' 990 7';
                    }
                }
            } else {
                return false;
            }
        }

        return $code;
    }

    /**
     * Ставка акциза
     *
     * @param boolean $privateUse
     * @param boolean $lightVehicle
     * @param string  $ageClass
     * @param string  $fueltype
     * @param integer $power
     *
     * @return integer
     */
    public static function getExciseRate($privateUse, $lightVehicle, $ageClass, $fueltype, $power)
    {
        $rate = 0;
        if (
            !self::isETSApplied($privateUse, $lightVehicle, $ageClass, $fueltype == self::TYPE_ELECTRO)
            && $lightVehicle
            && $power > 90
        ) {
            if ($power <= 150) {
                $rate = 47;
            } elseif ($power <= 200) {
                $rate = 454;
            } elseif ($power <= 300) {
                $rate = 743;
            } elseif ($power <= 400) {
                $rate = 1267;
            } elseif ($power <= 500) {
                $rate = 1310;
            } else {
                $rate = 1354;
            }
        }

        return $rate;
    }
}