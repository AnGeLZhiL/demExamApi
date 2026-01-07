<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UniversityParserController extends Controller
{
    /**
     * Поиск групп на портале НОВГУ
     */
    public function searchGroups(Request $request)
    {
        $searchTerm = $request->input('search', '');
        
        if (empty($searchTerm)) {
            return response()->json([
                'success' => false,
                'message' => 'Не указан поисковый запрос',
                'groups' => []
            ]);
        }
        
        try {
            Log::info('UniversityParser: поиск групп', ['search' => $searchTerm]);
            
            // URL портала НОВГУ
            $url = "https://portal.novsu.ru/search/groups/r.2500.p.search.g.3921/i.2500/?page=search&grpname=" . urlencode($searchTerm);
            
            // Делаем запрос через Laravel HTTP клиент
            $response = Http::withOptions([
                'verify' => false, // Отключаем SSL проверку при необходимости
                'timeout' => 10,
            ])->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'Accept-Encoding' => 'gzip, deflate, br',
            ])->get($url);
            
            if (!$response->successful()) {
                Log::error('UniversityParser: ошибка HTTP', [
                    'status' => $response->status(),
                    'url' => $url
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Ошибка при запросе к порталу университета',
                    'groups' => []
                ]);
            }
            
            $html = $response->body();
            
            // Парсим HTML
            $groups = $this->parseGroupsFromHTML($html, $searchTerm);
            
            Log::info('UniversityParser: найдено групп', ['count' => count($groups)]);
            
            return response()->json([
                'success' => true,
                'message' => 'Группы найдены',
                'groups' => $groups
            ]);
            
        } catch (\Exception $e) {
            Log::error('UniversityParser: исключение', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Ошибка при обработке запроса: ' . $e->getMessage(),
                'groups' => []
            ]);
        }
    }
    
    /**
     * Парсинг HTML для извлечения групп
     */
    private function parseGroupsFromHTML($html, $searchTerm)
    {
        $groups = [];
        
        try {
            // Создаем DOM документ
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
            $xpath = new \DOMXPath($dom);
            
            // Ищем все таблицы с классами viewtable
            $tables = $xpath->query("//table[contains(@class, 'viewtable')]");
            
            foreach ($tables as $tableIndex => $table) {
                // Ищем заголовок H3 перед таблицей
                $h3 = $this->findPreviousH3($table);
                
                if ($h3) {
                    $groupName = $this->extractGroupNameFromH3($h3);
                    
                    if ($groupName) {
                        $groupInfo = $this->extractGroupInfo($table);
                        $students = $this->extractStudentsFromTable($table);
                        
                        $groups[] = [
                            'id' => $tableIndex + 1,
                            'number' => $groupName,
                            'admission_year' => $groupInfo['admission_year'] ?? '',
                            'course' => $groupInfo['course'] ?? '',
                            'direction' => $groupInfo['direction'] ?? '',
                            'profile' => $groupInfo['profile'] ?? '',
                            'institute' => $groupInfo['institute'] ?? '',
                            'form' => $groupInfo['form'] ?? '',
                            'students_count' => count($students),
                            'students' => $students,
                            'source' => 'novsu_portal',
                            'search_term' => $searchTerm
                        ];
                    }
                }
            }
            
            // Если не нашли таблиц, возможно это страница одной группы
            if (empty($groups)) {
                $singleGroup = $this->extractSingleGroupInfo($dom, $searchTerm);
                if ($singleGroup) {
                    $groups[] = $singleGroup;
                }
            }
            
        } catch (\Exception $e) {
            Log::error('UniversityParser: ошибка парсинга HTML', [
                'error' => $e->getMessage()
            ]);
        }
        
        return $groups;
    }
    
    /**
     * Ищет предыдущий элемент H3
     */
    private function findPreviousH3($element)
    {
        $previous = $element->previousSibling;
        
        while ($previous) {
            if ($previous instanceof \DOMElement && $previous->tagName === 'h3') {
                return $previous;
            }
            $previous = $previous->previousSibling;
        }
        
        return null;
    }
    
    /**
     * Извлекает номер группы из H3
     */
    private function extractGroupNameFromH3($h3)
    {
        $text = $h3->textContent;
        
        if (preg_match('/Группа:\s*(.+)/u', $text, $matches)) {
            return trim($matches[1]);
        }
        
        return null;
    }
    
    /**
     * Извлекает информацию о группе
     */
    private function extractGroupInfo($table)
    {
        $info = [
            'admission_year' => '',
            'course' => '',
            'direction' => '',
            'profile' => '',
            'institute' => '',
            'form' => ''
        ];
        
        try {
            // Ищем UL перед таблицей
            $current = $table->previousSibling;
            
            while ($current) {
                if ($current instanceof \DOMElement && $current->tagName === 'ul') {
                    $items = $current->getElementsByTagName('li');
                    
                    foreach ($items as $item) {
                        $text = trim($item->textContent);
                        
                        if (str_contains($text, 'Год поступления:')) {
                            $info['admission_year'] = trim(str_replace('Год поступления:', '', $text));
                        } elseif (str_contains($text, 'Курс:')) {
                            $info['course'] = trim(str_replace('Курс:', '', $text));
                        } elseif (str_contains($text, 'Направление (специальность):')) {
                            $info['direction'] = trim(str_replace('Направление (специальность):', '', $text));
                        } elseif (str_contains($text, 'Профиль:')) {
                            $info['profile'] = trim(str_replace('Профиль:', '', $text));
                        } elseif (str_contains($text, 'Институт:')) {
                            $info['institute'] = trim(str_replace('Институт:', '', $text));
                        } elseif (str_contains($text, 'Форма обучения:')) {
                            $info['form'] = trim(str_replace('Форма обучения:', '', $text));
                        }
                    }
                    break;
                }
                $current = $current->previousSibling;
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки парсинга
        }
        
        return $info;
    }
    
    /**
     * Извлекает информацию о группе со страницы
     */
    private function extractSingleGroupInfo($dom, $searchTerm)
    {
        try {
            $xpath = new \DOMXPath($dom);
            
            // Ищем H3 с номером группы
            $h3s = $xpath->query("//h3[contains(text(), 'Группа:')]");
            
            if ($h3s->length > 0) {
                $h3 = $h3s->item(0);
                $groupName = $this->extractGroupNameFromH3($h3);
                
                if ($groupName) {
                    // Ищем таблицу студентов
                    $tables = $xpath->query("//table[contains(@class, 'viewtable')]");
                    
                    if ($tables->length > 0) {
                        $table = $tables->item(0);
                        $groupInfo = $this->extractGroupInfo($table);
                        $students = $this->extractStudentsFromTable($table);
                        
                        return [
                            'id' => 1,
                            'number' => $groupName,
                            'admission_year' => $groupInfo['admission_year'] ?? '',
                            'course' => $groupInfo['course'] ?? '',
                            'direction' => $groupInfo['direction'] ?? '',
                            'profile' => $groupInfo['profile'] ?? '',
                            'institute' => $groupInfo['institute'] ?? '',
                            'form' => $groupInfo['form'] ?? '',
                            'students_count' => count($students),
                            'students' => $students,
                            'source' => 'novsu_portal',
                            'search_term' => $searchTerm
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
        
        return null;
    }
    
    /**
     * Извлекает студентов из таблицы
     */
    private function extractStudentsFromTable($table)
    {
        $students = [];
        
        try {
            $rows = $table->getElementsByTagName('tr');
            
            foreach ($rows as $rowIndex => $row) {
                // Пропускаем заголовок
                if ($rowIndex === 0) continue;
                
                $cells = $row->getElementsByTagName('td');
                
                if ($cells->length >= 3) {
                    $number = trim($cells->item(0)->textContent);
                    
                    // Ищем ссылку с именем
                    $links = $cells->item(1)->getElementsByTagName('a');
                    $name = '';
                    $personId = null;
                    
                    if ($links->length > 0) {
                        $link = $links->item(0);
                        $name = trim($link->textContent);
                        
                        // Извлекаем ID из ссылки
                        $href = $link->getAttribute('href');
                        if (preg_match('/\/person\/(\d+)/', $href, $matches)) {
                            $personId = $matches[1];
                        }
                    }
                    
                    $status = trim($cells->item(2)->textContent);
                    
                    if ($name) {
                        $students[] = [
                            'number' => intval($number) ?: 0,
                            'full_name' => $name,
                            'status' => $status,
                            'person_id' => $personId
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            // Игнорируем ошибки
        }
        
        return $students;
    }
}